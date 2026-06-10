<?php

namespace App\Http\Controllers;

use App\Models\DiscountApproval;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use App\Services\PosService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AppController extends Controller
{
    public function ownerDashboard(): View
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $sales = Sale::query()->with('cashier', 'store')->latest()->get();
        $lowStock = Product::query()->where('stock', '<=', 1)->orderBy('stock')->get();

        // Build 7-day chart data: last 7 days including today, oldest first
        $chartData = collect(range(6, 0))->map(function (int $daysAgo): array {
            $date = now()->subDays($daysAgo)->startOfDay();
            return [
                'label'    => $date->locale('id')->isoFormat('ddd DD'),
                'date'     => $date->toDateString(),
                'total'    => 0,
                'is_today' => $daysAgo === 0,
            ];
        })->keyBy('date');

        Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->get()
            ->groupBy(fn(Sale $s) => $s->created_at->toDateString())
            ->each(function ($daySales, string $date) use (&$chartData): void {
                if ($chartData->has($date)) {
                    $entry = $chartData->get($date);
                    $entry['total'] = (int) $daySales->sum('total');
                    $chartData->put($date, $entry);
                }
            });

        $chartData  = $chartData->values()->toArray();
        $chartMax   = max(1, ...array_column($chartData, 'total'));

        return view('app.owner-dashboard', [
            'sales'           => $sales,
            'lowStock'        => $lowStock,
            'recentDiscounts' => DiscountApproval::query()->with('requester')->latest()->take(8)->get(),
            'notifications'   => Notification::query()->whereNull('read_at')->latest()->take(20)->get(),
            'unreadCount'     => Notification::query()->whereNull('read_at')->count(),
            'products'        => Product::query()->with('store')->orderBy('name')->get(),
            'productsCount'   => Product::query()->count(),
            'cashiersCount'   => User::query()->where('role', 'cashier')->count(),
            'summary'         => [
                'revenue'      => $sales->where('status', 'completed')->sum('total'),
                'profit'       => $sales->where('status', 'completed')->sum('profit'),
                'discounts'    => $sales->where('status', 'completed')->sum('discount_amount'),
                'transactions' => $sales->where('status', 'completed')->count(),
            ],
            'chartData'       => $chartData,
            'chartMax'        => $chartMax,
        ]);
    }

    public function pos(): View
    {
        return view('app.pos', [
            'products' => Product::query()->with('store')->orderBy('name')->get(),
        ]);
    }

    public function cashierHistory(): View
    {
        return view('app.cashier-history', [
            'sales' => Sale::query()
                ->with('items', 'discount', 'corrections.requester')
                ->where('user_id', auth()->id())
                ->latest()
                ->paginate(10),
        ]);
    }

    public function products(): View
    {
        return view('app.products', [
            'products' => Product::query()->with('store')->latest()->get(),
            'stores' => Store::query()->orderBy('name')->get(),
            'categories' => $this->categories(),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string'],
            'color' => ['nullable', 'string', 'max:60'],
            'size' => ['nullable', 'string', 'max:40'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'cost_price' => ['required', 'integer', 'min:0'],
            'selling_price' => ['required', 'integer', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
        ]);

        $data['min_stock'] = 0;

        $store = Store::query()->findOrFail($data['store_id']);
        $data['sku'] = $this->sku($store->code, $data['category'], $data['color'], $data['size']);
        Product::query()->create($data);

        return back()->with('status', 'Barang berhasil ditambahkan.');
    }

    public function purchase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'integer', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);
        $product->increment('stock', $data['qty']);
        $product->update(['cost_price' => $data['unit_cost'], 'supplier' => $data['supplier'] ?? $product->supplier]);
        $product->purchases()->create($data + ['user_id' => auth()->id()]);

        return back()->with('status', 'Pembelian/restock berhasil dicatat.');
    }

    public function checkout(Request $request, PosService $service): RedirectResponse
    {
        $payload = $request->validate([
            'payment_method' => ['required', 'string'],
            'amount_paid' => ['required', 'integer', 'min:0'],
            'discount_type' => ['nullable', 'in:amount,percent'],
            'discount_value' => ['nullable', 'integer', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $sale = $service->checkout(auth()->user(), $payload);
        } catch (\RuntimeException $exception) {
            return back()->withInput()->withErrors(['checkout' => $exception->getMessage()]);
        }

        return redirect()
            ->route('cashier.pos')
            ->with('status', "Transaksi {$sale->invoice_number} berhasil disimpan.")
            ->with('print_sale_id', $sale->id);
    }

    public function selfVoid(Request $request, Sale $sale, PosService $service): RedirectResponse
    {
        abort_unless($sale->user_id === auth()->id(), 403);

        $payload = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);

        try {
            $service->selfVoid(auth()->user(), $sale, $payload['reason']);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['void' => $exception->getMessage()]);
        }

        return back()->with('status', "Transaksi {$sale->invoice_number} berhasil dibatalkan dan stok dikembalikan.");
    }

    public function markNotificationsRead(): RedirectResponse
    {
        abort_unless(auth()->user()->isOwner(), 403);

        Notification::query()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Semua notifikasi telah ditandai dibaca.');
    }

    public function ownerRestock(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'qty' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'integer', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);
        $product->increment('stock', $data['qty']);
        $product->update([
            'cost_price' => $data['unit_cost'],
            'supplier' => $data['supplier'] ?? $product->supplier,
        ]);
        $product->purchases()->create($data + ['user_id' => auth()->id()]);

        // Resolve notifikasi low_stock untuk produk ini
        Notification::query()
            ->where('type', 'low_stock')
            ->whereNull('read_at')
            ->whereJsonContains('data->product_id', $product->id)
            ->update(['read_at' => now()]);

        return back()->with('status', "Restock {$product->name} (+{$data['qty']} pcs) berhasil dicatat.");
    }

    public function reports(): View
    {
        abort_unless(auth()->user()->isOwner(), 403);

        return view('app.reports', [
            'sales' => Sale::query()->with('items', 'cashier', 'store', 'discount', 'corrections.requester')->latest()->paginate(10, ['*'], 'sales_page'),
            'products' => Product::query()->with('store')->orderBy('stock')->paginate(10, ['*'], 'products_page'),
            'summary' => [
                'revenue' => Sale::query()->where('status', 'completed')->sum('total'),
                'profit' => Sale::query()->where('status', 'completed')->sum('profit'),
                'cogs' => Sale::query()->where('status', 'completed')->sum('cogs'),
                'items' => (int) \App\Models\SaleItem::query()
                    ->whereHas('sale', fn ($query) => $query->where('status', 'completed'))
                    ->sum('qty'),
            ],
        ]);
    }

    public function settings(SettingsService $settings): View
    {
        abort_unless(auth()->user()->isOwner(), 403);

        return view('app.owner-settings', [
            'settings' => $settings->all(),
        ]);
    }

    public function saveSettings(Request $request, SettingsService $settings): RedirectResponse
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $data = $request->validate([
            'store_name'         => ['required', 'string', 'max:120'],
            'store_address'      => ['nullable', 'string', 'max:255'],
            'store_phone'        => ['nullable', 'string', 'max:20'],
            'receipt_footer'     => ['nullable', 'string', 'max:255'],
            'owner_email'        => ['nullable', 'email', 'max:120'],
            'store_open_time'    => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'store_close_time'   => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
            'auto_print_receipt' => ['nullable', 'in:0,1'],
        ]);

        // Checkbox unchecked = absent from request → default to '0'
        $data['auto_print_receipt'] = $request->has('auto_print_receipt') ? '1' : '0';

        $settings->setMany($data);

        return redirect()->route('owner.settings')->with('status', 'Pengaturan berhasil disimpan.');
    }

    public function receipt(Request $request, Sale $sale, SettingsService $settings): View
    {
        abort_unless(
            $sale->user_id === auth()->id() || auth()->user()->isOwner(),
            403
        );

        $sale->load('items.product', 'cashier', 'store');

        return view('app.receipt', [
            'sale'      => $sale,
            'settings'  => $settings->all(),
            'autoPrint' => !$request->boolean('reprint') && $settings->getBool('auto_print_receipt', true),
        ]);
    }

    private function categories(): array
    {
        return ['celana', 'rok', 'vest', 'kemeja', 'gaun', 'one set', 'tunik', 'kaos rajut', 'sendal', 'tas', 'gasper', 'sepatu', 'kacamata', 'kaos', 'manset'];
    }

    private function sku(string $storeCode, string $category, ?string $color, ?string $size): string
    {
        return Str::upper(implode('-', [
            $storeCode,
            Str::substr(Str::slug($category, ''), 0, 3),
            Str::substr(Str::slug($color ?: 'mix', ''), 0, 3),
            Str::substr(Str::slug($size ?: 'all', ''), 0, 3),
            Str::random(4),
        ]));
    }
}

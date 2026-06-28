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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AppController extends Controller
{
    public function ownerDashboard(): View
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $lowStock = Product::query()->where('stock', '<=', 3)->orderBy('stock')->get();

        // Build 7-day chart data
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
            ->get(['id', 'total', 'created_at'])
            ->groupBy(fn(Sale $s) => $s->created_at->toDateString())
            ->each(function ($daySales, string $date) use (&$chartData): void {
                if ($chartData->has($date)) {
                    $entry = $chartData->get($date);
                    $entry['total'] = (int) $daySales->sum('total');
                    $chartData->put($date, $entry);
                }
            });

        $chartData = $chartData->values()->toArray();
        $chartMax  = max(1, ...array_column($chartData, 'total'));

        // Aggregates via DB — no full collection load
        $summary = [
            'revenue'      => Sale::query()->where('status', 'completed')->sum('total'),
            'profit'       => Sale::query()->where('status', 'completed')->sum('profit'),
            'discounts'    => Sale::query()->where('status', 'completed')->sum('discount_amount'),
            'transactions' => Sale::query()->where('status', 'completed')->count(),
        ];

        $recentSales = Sale::query()
            ->with('cashier:id,name')
            ->latest()
            ->limit(10)
            ->get(['id', 'invoice_number', 'user_id', 'total', 'status', 'created_at']);

        return view('app.owner-dashboard', [
            'recentSales'     => $recentSales,
            'lowStock'        => $lowStock,
            'recentDiscounts' => DiscountApproval::query()->with('requester:id,name')->latest()->take(8)->get(),
            'notifications'   => ($notifications = Notification::query()->whereNull('read_at')->latest()->take(20)->get()),
            'unreadCount'     => $notifications->count(),
            'products'        => Product::query()->with('store:id,name')->orderBy('name')->get(['id', 'name', 'stock', 'store_id']),
            'productsCount'   => Product::query()->count(),
            'cashiersCount'   => User::query()->where('role', 'cashier')->count(),
            'summary'         => $summary,
            'chartData'       => $chartData,
            'chartMax'        => $chartMax,
        ]);
    }

    public function ownerHistory(): View
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $search   = request('search');
        $dateFrom = request('date_from');
        $dateTo   = request('date_to');

        $sales = Sale::query()
            ->with('items', 'cashier', 'discount', 'corrections.requester')
            ->when($search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                      ->orWhereHas('cashier', fn($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($dateFrom, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($dateTo,   fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('app.owner-history', [
            'sales'    => $sales,
            'search'   => $search,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
        ]);
    }

    public function pos(): View
    {
        return view('app.pos', [
            'products' => Product::query()
                ->with('store:id,name')
                ->where('stock', '>', 0)
                ->orderBy('name')
                ->get(['id', 'name', 'sku', 'category', 'color', 'size', 'selling_price', 'cost_price', 'stock', 'store_id']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function cashierHistory(): View
    {
        $search   = request('search');
        $dateFrom = request('date_from');
        $dateTo   = request('date_to');

        $sales = Sale::query()
            ->with('items', 'discount', 'corrections.requester')
            ->where('user_id', auth()->id())
            ->when($search, fn($q, $search) => $q->where('invoice_number', 'like', "%{$search}%"))
            ->when($dateFrom, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($dateTo,   fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('app.cashier-history', [
            'sales'    => $sales,
            'search'   => $search,
            'dateFrom' => $dateFrom,
            'dateTo'   => $dateTo,
        ]);
    }

    public function products(): View
    {
        $search = request('search');

        $products = Product::query()
            ->with('store')
            ->when($search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%")
                      ->orWhere('color', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('app.products', [
            'products'   => $products,
            'stores'     => Store::query()->orderBy('name')->get(),
            'categories' => $this->categories(),
            'search'     => $search,
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

    public function storeBulkProducts(Request $request): RedirectResponse
    {
        $rows = $request->validate([
            'rows'                 => ['required', 'array', 'min:1'],
            'rows.*.store_id'      => ['required', 'exists:stores,id'],
            'rows.*.name'          => ['required', 'string', 'max:120'],
            'rows.*.category'      => ['required', 'string'],
            'rows.*.color'         => ['nullable', 'string', 'max:60'],
            'rows.*.size'          => ['nullable', 'string', 'max:40'],
            'rows.*.supplier'      => ['nullable', 'string', 'max:120'],
            'rows.*.cost_price'    => ['required', 'integer', 'min:0'],
            'rows.*.selling_price' => ['required', 'integer', 'min:0'],
            'rows.*.stock'         => ['required', 'integer', 'min:0'],
        ])['rows'];

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $store          = Store::query()->findOrFail($row['store_id']);
                $row['sku']     = $this->sku($store->code, $row['category'], $row['color'] ?? null, $row['size'] ?? null);
                $row['min_stock'] = 0;
                Product::query()->create($row);
            }
        });

        $count = count($rows);

        return back()->with('status', "{$count} barang berhasil ditambahkan.");
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

        $searchSale = request('search_sale');
        $dateFrom   = request('date_from');
        $dateTo     = request('date_to');

        $sales = Sale::query()
            ->with('items', 'cashier', 'store', 'discount', 'corrections.requester')
            ->when($searchSale, fn($q, $s) => $q->where('invoice_number', 'like', "%{$s}%"))
            ->when($dateFrom, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($dateTo,   fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(10, ['*'], 'sales_page')
            ->withQueryString();

        return view('app.reports', [
            'sales'      => $sales,
            'summary'    => [
                'revenue' => Sale::query()->where('status', 'completed')->sum('total'),
                'profit'  => Sale::query()->where('status', 'completed')->sum('profit'),
                'cogs'    => Sale::query()->where('status', 'completed')->sum('cogs'),
                'items'   => (int) \App\Models\SaleItem::query()
                    ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
                    ->where('sales.status', 'completed')
                    ->sum('sale_items.qty'),
            ],
            'searchSale' => $searchSale,
            'dateFrom'   => $dateFrom,
            'dateTo'     => $dateTo,
        ]);
    }

    public function settings(SettingsService $settings): View
    {
        abort_unless(auth()->user()->isOwner(), 403);

        return view('app.owner-settings', [
            'settings' => $settings->all(),
            'stores'   => Store::query()->orderBy('name')->get(['id', 'name', 'address']),
        ]);
    }

    public function updateStore(Request $request, Store $store): RedirectResponse
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $store->update($data);

        return redirect()->route('owner.settings')->with('status', "Detail {$store->name} berhasil disimpan.");
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
            'store_open_time'    => ['nullable', 'regex:/^([01][0-9]|2[0-3]):([0-5][0-9])$/'],
            'store_close_time'   => ['nullable', 'regex:/^([01][0-9]|2[0-3]):([0-5][0-9])$/'],
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

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:120'],
            'category'      => ['required', 'string'],
            'color'         => ['nullable', 'string', 'max:60'],
            'size'          => ['nullable', 'string', 'max:40'],
            'supplier'      => ['nullable', 'string', 'max:120'],
            'cost_price'    => ['required', 'integer', 'min:0'],
            'selling_price' => ['required', 'integer', 'min:0'],
            'stock'         => ['required', 'integer', 'min:0'],
            'store_id'      => ['required', 'exists:stores,id'],
        ]);

        $product->update($data);

        return redirect()->route('products.index')->with('status', "Barang {$product->name} berhasil diperbarui.");
    }

    public function destroyProduct(Product $product): RedirectResponse
    {
        abort_unless(auth()->user()->isOwner(), 403);

        try {
            $product->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->route('products.index')
                ->with('error', "Barang {$product->name} tidak dapat dihapus karena masih ada data transaksi terkait.");
        }

        return redirect()->route('products.index')->with('status', 'Barang berhasil dihapus.');
    }

    public function stockReport(): View
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $search = request('search_product');

        $products = Product::query()
            ->with('store:id,name')
            ->when($search, function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->where('name', 'like', "%{$s}%")
                      ->orWhere('sku', 'like', "%{$s}%")
                      ->orWhere('category', 'like', "%{$s}%");
                });
            })
            ->orderBy('stock')
            ->paginate(30)
            ->withQueryString();

        $stockSummary = [
            'total'  => Product::query()->count(),
            'habis'  => Product::query()->where('stock', 0)->count(),
            'kritis' => Product::query()->where('stock', '>', 0)->where('stock', '<=', 3)->count(),
            'aman'   => Product::query()->where('stock', '>', 3)->count(),
        ];

        return view('app.stock-report', [
            'products'      => $products,
            'searchProduct' => $search,
            'stockSummary'  => $stockSummary,
        ]);
    }

    public function users(): View
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $users = User::query()
            ->with('store')
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        $stores = Store::query()->orderBy('name')->get();

        return view('app.users', [
            'users'  => $users,
            'stores' => $stores,
        ]);
    }

    public function storeUser(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'email'    => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role'     => ['required', 'in:owner,cashier'],
            'store_id' => ['required', 'exists:stores,id'],
        ]);

        User::query()->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
            'role'     => $data['role'],
            'store_id' => $data['store_id'],
        ]);

        return redirect()->route('owner.users')->with('status', "Pengguna {$data['name']} berhasil dibuat.");
    }

    public function changeUserPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isOwner(), 403);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
        ]);

        return redirect()->route('owner.users')->with('status', "Password {$user->name} berhasil diubah.");
    }

    public function destroyUser(User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isOwner(), 403);
        abort_if($user->id === auth()->id(), 403, 'Tidak dapat menghapus akun sendiri.');

        $name = $user->name;
        $user->delete();

        return redirect()->route('owner.users')->with('status', "Pengguna {$name} berhasil dihapus.");
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

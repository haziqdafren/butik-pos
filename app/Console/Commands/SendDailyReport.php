<?php

namespace App\Console\Commands;

use App\Mail\DailyReportMail;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDailyReport extends Command
{
    protected $signature = 'report:daily';
    protected $description = 'Kirim laporan harian ke email owner';

    public function handle(SettingsService $settings): int
    {
        $configuredEmail = trim((string) $settings->get('owner_email', ''));
        $owners = User::query()->where('role', 'owner')->whereNotNull('email')->get();

        // Determine recipients
        if (empty($configuredEmail) && $owners->isEmpty()) {
            Log::warning('report:daily — tidak ada owner email terdaftar, laporan tidak dikirim.');
            $this->warn('Tidak ada owner dengan email terdaftar.');
            return self::SUCCESS;
        }

        $today    = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $dateLabel = now()->translatedFormat('d F Y');

        $completedSales = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$today, $todayEnd])
            ->get(['id', 'total', 'profit']);

        $voidedSales = Sale::query()
            ->with('corrections', 'cashier:id,name')
            ->where('status', 'voided')
            ->whereBetween('updated_at', [$today, $todayEnd])
            ->get(['id', 'invoice_number', 'user_id', 'status']);

        $voids = $voidedSales->map(function (Sale $sale): array {
            $correction = $sale->corrections->first();
            return [
                'invoice' => $sale->invoice_number,
                'cashier' => $sale->cashier?->name ?? '-',
                'reason'  => $correction?->reason ?? '-',
            ];
        })->values()->take(10)->toArray();

        $totalVoids = $voidedSales->count();

        $lowStockQuery = Product::query()
            ->where('stock', '<=', 3)
            ->orderBy('stock')
            ->get(['name', 'stock', 'supplier']);

        $totalLowStocks = $lowStockQuery->count();

        $lowStocks = $lowStockQuery->take(10)->map(fn (Product $p): array => [
            'name'     => $p->name,
            'stock'    => $p->stock,
            'supplier' => $p->supplier,
        ])->toArray();

        // Category breakdown: qty sold + revenue per category today
        $categoryBreakdown = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.created_at', [$today, $todayEnd])
            ->groupBy('products.category')
            ->orderByDesc('qty')
            ->select([
                'products.category',
                DB::raw('SUM(sale_items.qty) as qty'),
                DB::raw('SUM(sale_items.line_total) as revenue'),
            ])
            ->get()
            ->map(fn ($row): array => [
                'category' => ucfirst((string) $row->category),
                'qty'      => (int) $row->qty,
                'revenue'  => (int) $row->revenue,
            ])
            ->toArray();

        $storeName = (string) $settings->get('store_name', config('app.name'));

        // Build recipient list: settings email takes priority over DB owner accounts
        if (!empty($configuredEmail)) {
            $recipients = collect([[
                'email' => $configuredEmail,
                'name'  => $owners->first()?->name ?? 'Owner',
            ]]);
        } else {
            $recipients = $owners->map(fn (User $u) => [
                'email' => $u->email,
                'name'  => $u->name,
            ]);
        }

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient['email'])->send(new DailyReportMail(
                    ownerName: $recipient['name'],
                    storeName: $storeName,
                    date: $dateLabel,
                    totalTransactions: $completedSales->count(),
                    revenue: (int) $completedSales->sum('total'),
                    profit: (int) $completedSales->sum('profit'),
                    categoryBreakdown: $categoryBreakdown,
                    voids: $voids,
                    totalVoids: $totalVoids,
                    lowStocks: $lowStocks,
                    totalLowStocks: $totalLowStocks,
                ));
                $this->info("Laporan dikirim ke {$recipient['email']}");
            } catch (\Throwable $e) {
                Log::error("Gagal kirim laporan harian ke {$recipient['email']}: {$e->getMessage()}");
                $this->error("Gagal kirim ke {$recipient['email']}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}

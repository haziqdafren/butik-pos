<?php

namespace App\Console\Commands;

use App\Mail\DailyReportMail;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDailyReport extends Command
{
    protected $signature = 'report:daily';
    protected $description = 'Kirim laporan harian ke email owner';

    public function handle(): int
    {
        $owners = User::query()->where('role', 'owner')->whereNotNull('email')->get();

        if ($owners->isEmpty()) {
            $this->warn('Tidak ada owner dengan email terdaftar.');
            return self::SUCCESS;
        }

        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $dateLabel = now()->translatedFormat('d F Y');

        $completedSales = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$today, $todayEnd])
            ->get();

        $voidedSales = Sale::query()
            ->with('corrections.requester', 'cashier')
            ->where('status', 'voided')
            ->whereBetween('updated_at', [$today, $todayEnd])
            ->get();

        $voids = $voidedSales->map(function (Sale $sale): array {
            $correction = $sale->corrections->firstWhere('status', 'self_voided');
            return [
                'invoice' => $sale->invoice_number,
                'cashier' => $sale->cashier->name,
                'reason' => $correction?->reason ?? '-',
            ];
        })->values()->toArray();

        $lowStocks = Product::query()
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->get()
            ->map(fn (Product $p): array => [
                'name' => $p->name,
                'stock' => $p->stock,
                'supplier' => $p->supplier,
            ])
            ->toArray();

        foreach ($owners as $owner) {
            try {
                Mail::to($owner->email)->send(new DailyReportMail(
                    ownerName: $owner->name,
                    date: $dateLabel,
                    totalTransactions: $completedSales->count(),
                    revenue: (int) $completedSales->sum('total'),
                    profit: (int) $completedSales->sum('profit'),
                    voids: $voids,
                    lowStocks: $lowStocks,
                ));
                $this->info("Laporan dikirim ke {$owner->email}");
            } catch (\Throwable $e) {
                Log::error("Gagal kirim laporan harian ke {$owner->email}: {$e->getMessage()}");
                $this->error("Gagal kirim ke {$owner->email}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}

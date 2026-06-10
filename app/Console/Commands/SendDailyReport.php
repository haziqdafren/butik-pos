<?php

namespace App\Console\Commands;

use App\Mail\DailyReportMail;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Console\Command;
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
                'reason'  => $correction?->reason ?? '-',
            ];
        })->values()->toArray();

        $lowStocks = Product::query()
            ->where('stock', '<=', 1)
            ->orderBy('stock')
            ->get()
            ->map(fn (Product $p): array => [
                'name'     => $p->name,
                'stock'    => $p->stock,
                'supplier' => $p->supplier,
            ])
            ->toArray();

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
                    date: $dateLabel,
                    totalTransactions: $completedSales->count(),
                    revenue: (int) $completedSales->sum('total'),
                    profit: (int) $completedSales->sum('profit'),
                    voids: $voids,
                    lowStocks: $lowStocks,
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

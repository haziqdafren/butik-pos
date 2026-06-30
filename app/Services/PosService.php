<?php

namespace App\Services;

use App\Models\DiscountApproval;
use App\Models\Notification;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleCorrection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PosService
{
    public function checkout(User $cashier, array $data): Sale
    {
        return DB::transaction(function () use ($cashier, $data): Sale {
            $totals = $this->calculateCart($data['items'], lock: true);
            $discount = $this->recordDiscount($cashier, $data, $totals['subtotal']);
            $discountAmount = $discount?->amount ?? 0;
            $total = max(0, $totals['subtotal'] - $discountAmount);
            $amountPaid = (int) $data['amount_paid'];

            if ($amountPaid < $total) {
                throw new RuntimeException('Pembayaran kurang dari total transaksi.');
            }

            $sale = Sale::query()->create([
                'invoice_number' => 'INV-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5)),
                'user_id' => $cashier->id,
                'store_id' => (int) $data['store_id'],
                'discount_approval_id' => $discount?->id,
                'subtotal' => $totals['subtotal'],
                'discount_amount' => $discountAmount,
                'total' => $total,
                'amount_paid' => $amountPaid,
                'change' => $amountPaid - $total,
                'payment_method' => $data['payment_method'],
                'cogs' => $totals['cogs'],
                'profit' => $total - $totals['cogs'],
            ]);

            foreach ($totals['products'] as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $qty = $line['qty'];
                $product->decrement('stock', $qty);

                $sale->items()->create([
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'qty' => $qty,
                    'unit_price' => $product->selling_price,
                    'unit_cost' => $product->cost_price,
                    'line_total' => $product->selling_price * $qty,
                ]);
            }

            foreach ($totals['products'] as $line) {
                $this->updateStockNotification($line['product']->fresh());
            }

            return $sale->refresh();
        });
    }

    public function requestCorrection(User $cashier, Sale $sale, array $data): SaleCorrection
    {
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            throw new RuntimeException('Alasan koreksi wajib diisi.');
        }

        if ($sale->status !== 'completed') {
            throw new RuntimeException('Transaksi ini sudah tidak aktif.');
        }

        if ($sale->corrections()->where('status', 'pending')->exists()) {
            throw new RuntimeException('Transaksi ini masih memiliki request koreksi tertunda.');
        }

        return SaleCorrection::query()->create([
            'sale_id' => $sale->id,
            'requested_by' => $cashier->id,
            'type' => $data['type'] ?? 'void',
            'status' => 'pending',
            'reason' => $reason,
        ]);
    }

    public function approveCorrection(User $owner, SaleCorrection $correction): SaleCorrection
    {
        if (! $owner->isOwner()) {
            throw new RuntimeException('Hanya owner yang dapat menyetujui koreksi transaksi.');
        }

        return DB::transaction(function () use ($owner, $correction): SaleCorrection {
            $correction = SaleCorrection::query()->with('sale.items.product')->lockForUpdate()->findOrFail($correction->id);

            if ($correction->status !== 'pending') {
                throw new RuntimeException('Request koreksi sudah diproses.');
            }

            $sale = $correction->sale;
            if ($sale->status !== 'completed') {
                throw new RuntimeException('Transaksi ini sudah tidak aktif.');
            }

            foreach ($sale->items as $item) {
                $item->product->increment('stock', $item->qty);
            }

            $sale->update(['status' => 'voided']);
            $correction->update([
                'approved_by' => $owner->id,
                'approved_at' => now(),
                'status' => 'approved',
            ]);

            return $correction->refresh();
        });
    }

    public function selfVoid(User $cashier, Sale $sale, string $reason): SaleCorrection
    {
        $reason = trim($reason);

        if ($sale->status !== 'completed') {
            throw new RuntimeException('Transaksi ini sudah dibatalkan.');
        }

        if (strlen($reason) < 8) {
            throw new RuntimeException('Alasan pembatalan minimal 8 karakter.');
        }

        return DB::transaction(function () use ($cashier, $sale, $reason): SaleCorrection {
            $sale = Sale::query()->with('items.product')->lockForUpdate()->findOrFail($sale->id);

            foreach ($sale->items as $item) {
                $item->product->increment('stock', $item->qty);
            }

            $sale->update(['status' => 'voided']);

            $correction = SaleCorrection::query()->create([
                'sale_id' => $sale->id,
                'requested_by' => $cashier->id,
                'approved_by' => null,
                'type' => 'void',
                'status' => 'self_voided',
                'reason' => $reason,
                'approved_at' => now(),
            ]);

            Notification::query()->create([
                'type' => 'void_transaction',
                'title' => 'Transaksi Dibatalkan',
                'body' => "Kasir {$cashier->name} membatalkan {$sale->invoice_number}. Alasan: {$reason}",
                'data' => ['sale_id' => $sale->id],
            ]);

            return $correction;
        });
    }

    private function updateStockNotification(Product $product): void
    {
        if ($product->stock === 0) {
            // Resolve any existing low_stock notification for this product
            Notification::query()
                ->where('type', 'low_stock')
                ->whereNull('read_at')
                ->whereJsonContains('data->product_id', $product->id)
                ->update(['read_at' => now()]);

            $this->createNotifIfAbsent(
                $product,
                'out_of_stock',
                'Stok Habis',
                "Stok {$product->name} sudah habis. Segera lakukan restock."
            );
        } elseif ($product->stock === 1) {
            $supplier = $product->supplier ?: '-';
            $this->createNotifIfAbsent(
                $product,
                'low_stock',
                'Stok Sedikit',
                "Stok {$product->name} tinggal 1 pcs. Supplier: {$supplier}."
            );
        }
    }

    private function createNotifIfAbsent(Product $product, string $type, string $title, string $body): void
    {
        $exists = Notification::query()
            ->where('type', $type)
            ->whereNull('read_at')
            ->whereJsonContains('data->product_id', $product->id)
            ->exists();

        if ($exists) {
            return;
        }

        Notification::query()->create([
            'type'  => $type,
            'title' => $title,
            'body'  => $body,
            'data'  => ['product_id' => $product->id],
        ]);
    }

    private function calculateCart(array $items, bool $lock = false): array
    {
        $subtotal = 0;
        $cogs = 0;
        $products = [];

        foreach ($items as $item) {
            $query = Product::query()->whereKey($item['product_id']);
            $product = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
            $qty = (int) $item['qty'];

            if ($qty < 1) {
                throw new RuntimeException('Jumlah barang harus lebih dari 0.');
            }

            if ($product->stock < $qty) {
                throw new RuntimeException("Stok tidak cukup untuk {$product->name}.");
            }

            $subtotal += $product->selling_price * $qty;
            $cogs += $product->cost_price * $qty;
            $products[] = ['product' => $product, 'qty' => $qty];
        }

        if ($subtotal === 0) {
            throw new RuntimeException('Keranjang masih kosong.');
        }

        return compact('subtotal', 'cogs', 'products');
    }

    private function recordDiscount(User $cashier, array $data, int $subtotal): ?DiscountApproval
    {
        $value = (int) ($data['discount_value'] ?? 0);

        if ($value <= 0) {
            return null;
        }

        $reason = trim((string) ($data['discount_reason'] ?? ''));
        if ($reason === '') {
            throw new RuntimeException('Alasan diskon wajib diisi agar owner dapat meninjau transaksi.');
        }

        $type = $data['discount_type'] ?? 'amount';
        $amount = $this->discountAmount($type, $value, $subtotal);

        return DiscountApproval::query()->create([
            'requested_by' => $cashier->id,
            'type' => $type,
            'value' => $value,
            'subtotal' => $subtotal,
            'amount' => $amount,
            'reason' => $reason,
            'status' => 'recorded',
        ]);
    }

    private function discountAmount(string $type, int $value, int $subtotal): int
    {
        $amount = $type === 'percent'
            ? (int) round($subtotal * min($value, 100) / 100)
            : $value;

        return min($amount, $subtotal);
    }
}

<x-layouts.app title="History Transaksi">
    <section class="card">
        <div class="toolbar" style="justify-content:space-between; align-items:center">
            <h3 style="margin:0">History Transaksi</h3>
        </div>
        <p class="muted">Semua transaksi dari seluruh kasir. Klik Detail untuk melihat item dan informasi pembayaran.</p>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Invoice</th><th>Kasir</th><th>Waktu</th><th>Item</th><th>Diskon</th><th>Total</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td>{{ $sale->invoice_number }}</td>
                        <td>{{ $sale->cashier?->name ?? '-' }}</td>
                        <td>{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $sale->items->sum('qty') }}</td>
                        <td>Rp {{ number_format($sale->discount_amount, 0, ',', '.') }}</td>
                        <td class="money">Rp {{ number_format($sale->total, 0, ',', '.') }}</td>
                        <td>
                            @if($sale->status === 'voided')
                                <span class="badge red">Dibatalkan</span>
                            @else
                                <span class="badge green">Selesai</span>
                            @endif
                        </td>
                        <td>
                            <div class="row-actions">
                                <label class="button secondary mini" for="owner-hist-detail-{{ $sale->id }}">Detail</label>
                                @if($sale->status === 'completed')
                                    <button type="button" class="button secondary mini"
                                            onclick="(function(){
                                                var p = window.open('/kasir/struk/{{ $sale->id }}?reprint=1', 'receipt_{{ $sale->id }}', 'width=340,height=700,scrollbars=yes,resizable=yes');
                                                if (!p) window.open('/kasir/struk/{{ $sale->id }}?reprint=1', '_blank');
                                            })()">Cetak</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Belum ada transaksi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$sales" />
    </section>

    @foreach($sales as $sale)
        <input class="modal-toggle" type="checkbox" id="owner-hist-detail-{{ $sale->id }}">
        <div class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Detail {{ $sale->invoice_number }}</h3>
                    <label class="button secondary mini" for="owner-hist-detail-{{ $sale->id }}">Tutup</label>
                </div>
                <div class="modal-body">
                    @if($sale->status === 'voided')
                        <div class="notice" style="background:#fde8e8;color:#b91c1c;border-color:#f87171">
                            Transaksi ini sudah dibatalkan.
                            @php($voidRecord = $sale->corrections->firstWhere('status', 'self_voided'))
                            @if($voidRecord)
                                Alasan: <strong>{{ $voidRecord->reason }}</strong>
                            @endif
                        </div>
                    @endif
                    <div class="detail-grid">
                        <div class="detail-box"><small>Kasir</small><strong>{{ $sale->cashier?->name ?? '-' }}</strong></div>
                        <div class="detail-box"><small>Subtotal</small><strong>Rp {{ number_format($sale->subtotal, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Diskon</small><strong>Rp {{ number_format($sale->discount_amount, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Total</small><strong>Rp {{ number_format($sale->total, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Dibayar</small><strong>Rp {{ number_format($sale->amount_paid, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Kembalian</small><strong>Rp {{ number_format($sale->change, 0, ',', '.') }}</strong></div>
                        <div class="detail-box"><small>Metode</small><strong>{{ $sale->payment_method }}</strong></div>
                    </div>
                    <table>
                        <thead><tr><th>SKU</th><th>Barang</th><th>Qty</th><th>Harga Satuan</th><th>Total</th></tr></thead>
                        <tbody>
                        @foreach($sale->items as $item)
                            <tr>
                                <td>{{ $item->sku }}</td>
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->qty }}</td>
                                <td>Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                                <td>Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    @if($sale->discount)
                        <p style="margin-top:8px"><strong>Catatan diskon:</strong> {{ $sale->discount->reason }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</x-layouts.app>

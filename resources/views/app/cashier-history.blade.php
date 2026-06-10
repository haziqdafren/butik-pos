<x-layouts.app title="History Transaksi Kasir">
    <section class="card">
        <div class="toolbar" style="justify-content:space-between; align-items:center">
            <h3 style="margin:0">Transaksi Saya</h3>
        </div>
        <p class="muted">Jika ada kesalahan input, klik <strong>Batalkan</strong> dan isi alasan dengan jelas. Transaksi akan langsung dibatalkan dan stok dikembalikan.</p>
        <form method="get" action="{{ route('cashier.history') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
            <input class="input" type="search" name="search" value="{{ $search ?? '' }}" placeholder="Cari no. invoice..." style="min-width:180px">
            <input class="input" type="date" name="date_from" value="{{ $dateFrom ?? '' }}" style="width:140px">
            <input class="input" type="date" name="date_to" value="{{ $dateTo ?? '' }}" style="width:140px">
            <button class="button secondary" type="submit">Filter</button>
            @if($search || $dateFrom || $dateTo)
                <a href="{{ route('cashier.history') }}" class="button secondary">Reset</a>
            @endif
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                <tr><th>Invoice</th><th>Waktu</th><th>Item</th><th>Diskon</th><th>Total</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td>{{ $sale->invoice_number }}</td>
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
                                <label class="button secondary mini" for="sale-detail-{{ $sale->id }}">Detail</label>
                                @if($sale->status === 'completed')
                                    <button type="button"
                                            id="cetak-btn-{{ $sale->id }}"
                                            class="button secondary mini"
                                            onclick="(function(){
                                                var p = window.open('/kasir/struk/{{ $sale->id }}?reprint=1', 'receipt_{{ $sale->id }}', 'width=340,height=700,scrollbars=yes,resizable=yes');
                                                if (!p) window.open('/kasir/struk/{{ $sale->id }}?reprint=1', '_blank');
                                            })()">Cetak</button>
                                    <label class="button danger mini" for="sale-void-{{ $sale->id }}">Batalkan</label>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Belum ada transaksi.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-pager :paginator="$sales" />
    </section>

    @foreach($sales as $sale)
        {{-- Modal Detail --}}
        <input class="modal-toggle" type="checkbox" id="sale-detail-{{ $sale->id }}">
        <div class="modal">
            <div class="modal-card">
                <div class="modal-head">
                    <h3>Detail {{ $sale->invoice_number }}</h3>
                    <label class="button secondary mini" for="sale-detail-{{ $sale->id }}">Tutup</label>
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

        {{-- Modal Void --}}
        @if($sale->status === 'completed')
            <input class="modal-toggle" type="checkbox" id="sale-void-{{ $sale->id }}">
            <div class="modal">
                <div class="modal-card">
                    <div class="modal-head">
                        <h3>Batalkan Transaksi</h3>
                        <label class="button secondary mini" for="sale-void-{{ $sale->id }}">Tutup</label>
                    </div>
                    <div class="modal-body">
                        <div class="notice" style="background:#fef3c7;color:#92400e;border-color:#fbbf24">
                            <strong>Perhatian:</strong> Setelah dibatalkan, transaksi <strong>{{ $sale->invoice_number }}</strong> tidak bisa diaktifkan kembali. Stok barang akan otomatis dikembalikan.
                        </div>
                        <form method="post" action="{{ route('sales.self-void', $sale) }}">
                            @csrf
                            <div class="field" style="margin-top:12px">
                                <label>Alasan Pembatalan <span style="color:red">*</span></label>
                                <textarea class="input" name="reason" required minlength="8" maxlength="500" rows="3" placeholder="Contoh: Salah pilih ukuran, customer minta batal, salah input barang..."></textarea>
                                <small class="muted">Minimal 8 karakter. Alasan ini akan tercatat dan dilihat owner.</small>
                            </div>
                            <div class="row-actions" style="margin-top:12px">
                                <label class="button secondary" for="sale-void-{{ $sale->id }}">Batal</label>
                                <button class="button danger" type="submit">Ya, Batalkan Transaksi</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</x-layouts.app>

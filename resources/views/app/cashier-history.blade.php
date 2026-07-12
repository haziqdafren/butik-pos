<x-layouts.app title="History Transaksi Kasir">
    <section class="card">
        <div class="toolbar" style="justify-content:space-between; align-items:center">
            <h3 style="margin:0">Semua Transaksi</h3>
        </div>
        <p class="muted">Semua transaksi dari semua kasir. Jika ada kesalahan input, klik <strong>Batalkan</strong> dan isi alasan dengan jelas. Transaksi akan langsung dibatalkan dan stok dikembalikan.</p>
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
                <tr><th>Invoice</th><th>Waktu</th><th>Toko</th><th>Item</th><th>Diskon</th><th>Total</th><th>Status</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                @forelse($sales as $sale)
                    <tr>
                        <td>{{ $sale->invoice_number }}</td>
                        <td>{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                        <td style="font-size:12px;color:var(--muted)">{{ $sale->store?->name ?? '-' }}
                            @if($sale->stockSourceStore)
                                <br><small style="color:#92400e;font-size:10px">stok: {{ $sale->stockSourceStore->name }}</small>
                            @endif
                        </td>
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
                                            onclick="printReceiptBluetooth({{ $sale->id }}, this)">Cetak</button>
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

    <script>
    // ── Bluetooth helpers (reprint) ─────────────────────────────────────────────
    async function btFindPrintChar(server) {
        const services = await server.getPrimaryServices();
        for (const service of services) {
            const chars = await service.getCharacteristics();
            for (const char of chars) {
                if (char.properties.write || char.properties.writeWithoutResponse) return char;
            }
        }
        return null;
    }

    let _btDevice = null;
    let _btChar   = null;

    async function btGetPrinter() {
        if (_btDevice && _btDevice.gatt.connected && _btChar) {
            return { device: _btDevice, char: _btChar };
        }
        // Try getDevices() silently if user has paired before (no picker, no flash)
        const wasPaired = localStorage.getItem('bt_printer_paired') === '1';
        if (navigator.bluetooth.getDevices && wasPaired) {
            try {
                const paired  = await navigator.bluetooth.getDevices();
                const printer = paired.find(d => d.name === 'RPP02N');
                if (printer) {
                    const server = await printer.gatt.connect();
                    const char   = await btFindPrintChar(server);
                    if (char) {
                        _btDevice = printer;
                        _btChar   = char;
                        return { device: printer, char };
                    }
                }
            } catch (_) {}
        }
        const device = await navigator.bluetooth.requestDevice({
            filters: [{ name: 'RPP02N' }],
            optionalServices: [
                '000018f0-0000-1000-8000-00805f9b34fb',
                'e7810a71-73ae-499d-8c15-faa9aef0c3f2',
                '49535343-fe7d-4ae5-8fa9-9fafd205e455'
            ]
        });
        const server = await device.gatt.connect();
        const char   = await btFindPrintChar(server);
        if (!char) throw new Error('Printer tidak valid.');
        _btDevice = device;
        _btChar   = char;
        // Mark as paired so next page load skips the picker
        try { localStorage.setItem('bt_printer_paired', '1'); } catch(_) {}
        return { device, char };
    }

    async function btPrintReceipt(printChar, res) {
        const encoder = new TextEncoder();
        const data    = [];
        const push     = arr => data.push(...arr);
        const pushLine = str => data.push(...encoder.encode(str + '\n'));
        const fmtRp    = n   => 'Rp ' + new Intl.NumberFormat('id-ID').format(n);
        const fmtNum   = n   => new Intl.NumberFormat('id-ID').format(n);
        const pad      = (l, r, w) => l + ' '.repeat(Math.max(0, w - l.length - r.length)) + r;

        push([0x1B, 0x40]);
        push([0x1B, 0x61, 0x01]);
        pushLine(res.store_name);
        if (res.store_address) pushLine(res.store_address);
        if (res.store_phone)   pushLine(res.store_phone);
        pushLine('--------------------------------');
        push([0x1B, 0x61, 0x00]);
        pushLine('Kasir   : ' + res.cashier_name);
        pushLine('Tanggal : ' + res.created_at);
        pushLine('Invoice : ' + res.invoice_number);
        pushLine('--------------------------------');
        res.items.forEach(i => {
            pushLine(i.name);
            if (i.attrs && i.attrs.length) pushLine(i.attrs.join(' - '));
            pushLine(pad(i.qty + 'x', fmtRp(i.line_total), 32));
        });
        pushLine('--------------------------------');
        pushLine(pad('Subtotal', fmtNum(res.subtotal), 32));
        if (res.discount_amount > 0) pushLine(pad('Diskon', '- ' + fmtNum(res.discount_amount), 32));
        pushLine('--------------------------------');
        push([0x1B, 0x45, 0x01]);
        pushLine(pad('TOTAL', fmtRp(res.total), 32));
        push([0x1B, 0x45, 0x00]);
        pushLine('--------------------------------');
        pushLine(pad(res.payment_method, fmtNum(res.amount_paid), 32));
        pushLine(pad('Kembalian', fmtNum(res.change), 32));
        pushLine('================================');
        push([0x1B, 0x61, 0x01]);
        pushLine(res.receipt_footer || 'Terima kasih telah berbelanja!');
        push([0x0A, 0x0A, 0x0A]);

        const bytes = new Uint8Array(data);
        for (let i = 0; i < bytes.length; i += 100) {
            await printChar.writeValue(bytes.slice(i, i + 100));
        }
    }

    async function printReceiptBluetooth(saleId, btn) {
        if (!navigator.bluetooth) {
            // Fallback: open receipt popup
            var url = '/kasir/struk/' + saleId + '?reprint=1';
            var p = window.open(url, 'receipt_' + saleId, 'width=340,height=700,scrollbars=yes,resizable=yes');
            if (!p) window.open(url, '_blank');
            return;
        }

        const originalText = btn.textContent;
        btn.disabled  = true;
        btn.textContent = 'Memulai...';

        let printer;
        try {
            printer = await btGetPrinter();
        } catch (err) {
            btn.disabled  = false;
            btn.textContent = originalText;
            if (err.name !== 'NotFoundError') {
                if (typeof showToast === 'function') showToast('Koneksi printer gagal: ' + err.message, 'warn');
            }
            return;
        }

        btn.textContent = 'Mengambil...';

        try {
            const response = await fetch('/kasir/struk/' + saleId, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await response.json();

            if (!response.ok) {
                if (typeof showToast === 'function') showToast(res.message || 'Gagal mengambil struk', 'error');
                btn.disabled  = false;
                btn.textContent = originalText;
                if (printer.device.gatt.connected) printer.device.gatt.disconnect();
                return;
            }

            btn.textContent = 'Mencetak...';
            await btPrintReceipt(printer.char, res);

            if (typeof showToast === 'function') showToast('Struk berhasil dicetak!');
            setTimeout(() => {
                btn.disabled  = false;
                btn.textContent = originalText;
                if (printer.device.gatt.connected) printer.device.gatt.disconnect();
            }, 1500);

        } catch (err) {
            console.error(err);
            if (typeof showToast === 'function') showToast('Kesalahan cetak: ' + err.message, 'error');
            btn.disabled  = false;
            btn.textContent = originalText;
        }
    }
    </script>

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
                    @if($sale->stockSourceStore)
                        <div class="notice" style="background:#fef3c7;color:#92400e;border-color:#fbbf24;margin-bottom:8px">
                            Stok diambil dari: <strong>{{ $sale->stockSourceStore->name }}</strong>
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

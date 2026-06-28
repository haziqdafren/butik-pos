<x-layouts.app title="Pengaturan Toko">
    {{-- Store details per store --}}
    <section class="card" style="max-width:680px;margin-bottom:24px">
        <h3>Detail Toko</h3>
        @foreach($stores as $store)
        <form method="post" action="{{ route('owner.store.update', $store) }}" style="{{ !$loop->last ? 'margin-bottom:24px;padding-bottom:24px;border-bottom:1px solid var(--border)' : '' }}">
            @csrf
            @method('PATCH')
            <div style="font-size:12px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:10px;">{{ $store->name }}</div>
            <div class="grid-2" style="grid-template-columns:1fr 1fr">
                <div class="field">
                    <label>Nama Toko</label>
                    <input class="input" type="text" name="name"
                           value="{{ old('name', $store->name) }}"
                           maxlength="120" required>
                </div>
                <div class="field">
                    <label>Alamat</label>
                    <input class="input" type="text" name="address"
                           value="{{ old('address', $store->address) }}"
                           maxlength="255">
                </div>
            </div>
            <button class="button secondary" type="submit" style="font-size:13px;padding:6px 16px">Simpan</button>
        </form>
        @endforeach
    </section>

    <section class="card" style="max-width:680px">
        <h3>Pengaturan Umum</h3>

        <form method="post" action="{{ route('owner.settings.save') }}">
            @csrf

            <div class="field">
                <label>Nama Toko <span style="color:red">*</span></label>
                <input class="input" type="text" name="store_name"
                       value="{{ old('store_name', $settings['store_name']) }}"
                       maxlength="120" required>
            </div>

            <div class="field">
                <label>Alamat Toko</label>
                <input class="input" type="text" name="store_address"
                       value="{{ old('store_address', $settings['store_address']) }}"
                       maxlength="255">
            </div>

            <div class="field">
                <label>Nomor Telepon</label>
                <input class="input" type="text" name="store_phone"
                       value="{{ old('store_phone', $settings['store_phone']) }}"
                       maxlength="20">
            </div>

            <div class="field">
                <label>Pesan Footer Struk</label>
                <input class="input" type="text" name="receipt_footer"
                       value="{{ old('receipt_footer', $settings['receipt_footer']) }}"
                       maxlength="255"
                       placeholder="Terima kasih telah berbelanja!">
            </div>

            <div class="field">
                <label>Email Laporan Harian</label>
                <input class="input" type="email" name="owner_email"
                       value="{{ old('owner_email', $settings['owner_email']) }}"
                       maxlength="120">
                <small class="muted">Laporan harian dikirim ke alamat ini setiap hari pukul 20:00.</small>
            </div>

            <div class="grid-2" style="grid-template-columns:1fr 1fr">
                <div class="field">
                    <label>Jam Buka</label>
                    <input class="input" type="time" name="store_open_time"
                           value="{{ old('store_open_time', $settings['store_open_time']) }}">
                </div>
                <div class="field">
                    <label>Jam Tutup</label>
                    <input class="input" type="time" name="store_close_time"
                           value="{{ old('store_close_time', $settings['store_close_time']) }}">
                </div>
            </div>

            <div class="field" style="flex-direction:row;align-items:center;gap:10px;display:flex">
                <input type="checkbox" id="auto_print_receipt" name="auto_print_receipt"
                       value="1" {{ old('auto_print_receipt', $settings['auto_print_receipt'] ?? '1') == '1' ? 'checked' : '' }}
                       style="width:auto">
                <label for="auto_print_receipt" style="color:var(--ink);font-size:14px;font-weight:400">
                    Cetak struk otomatis setelah transaksi selesai
                </label>
            </div>

            <div style="margin-top:16px">
                <button class="button" type="submit">Simpan Pengaturan</button>
            </div>
        </form>
    </section>
</x-layouts.app>

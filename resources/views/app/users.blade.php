<x-layouts.app title="Kelola Pengguna">

    <div class="grid-2" style="gap:16px;align-items:start">

        {{-- ── Left: User list ───────────────────────────────── --}}
        <section class="card">
            <h3 style="margin-bottom:16px">Daftar Pengguna</h3>

            @if($users->isEmpty())
                <p class="muted">Belum ada pengguna.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Email</th>
                                <th class="col-hide-mobile">Toko</th>
                                <th>Role</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>
                                    <strong>{{ $user->name }}</strong>
                                    @if($user->id === auth()->id())
                                        <span class="badge" style="margin-left:4px;font-size:10px">Anda</span>
                                    @endif
                                </td>
                                <td class="muted" style="font-size:12px">{{ $user->email }}</td>
                                <td class="col-hide-mobile">{{ $user->store?->name ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $user->role === 'owner' ? 'amber' : 'green' }}">
                                        {{ $user->role === 'owner' ? 'Owner' : 'Kasir' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="row-actions">
                                        <label class="button secondary mini" for="modal-pwd-{{ $user->id }}" title="Ganti password">Ganti PW</label>
                                        @if($user->id !== auth()->id())
                                            <form method="post" action="{{ route('owner.users.destroy', $user) }}"
                                                  onsubmit="return confirmDelete(this, 'Hapus pengguna {{ addslashes($user->name) }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="button danger mini">Hapus</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- ── Right: Add user form ──────────────────────────── --}}
        <section class="card">
            <h3 style="margin-bottom:16px">Tambah Pengguna</h3>
            <form method="post" action="{{ route('owner.users.store') }}">
                @csrf
                <div class="field">
                    <label>Nama Lengkap <span style="color:red">*</span></label>
                    <input class="input" type="text" name="name" value="{{ old('name') }}" required maxlength="120" placeholder="Nama pengguna">
                </div>
                <div class="field">
                    <label>Email <span style="color:red">*</span></label>
                    <input class="input" type="email" name="email" value="{{ old('email') }}" required maxlength="120" placeholder="email@example.com">
                </div>
                <div class="field">
                    <label>Role <span style="color:red">*</span></label>
                    <select class="input" name="role" required>
                        <option value="cashier" @selected(old('role', 'cashier') === 'cashier')>Kasir</option>
                        <option value="owner" @selected(old('role') === 'owner')>Owner</option>
                    </select>
                </div>
                <div class="field">
                    <label>Toko <span style="color:red">*</span></label>
                    <select class="input" name="store_id" required>
                        @foreach($stores as $store)
                            <option value="{{ $store->id }}" @selected(old('store_id') == $store->id)>{{ $store->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Password <span style="color:red">*</span></label>
                    <input class="input" type="password" name="password" required minlength="8" placeholder="Min. 8 karakter">
                </div>
                <div class="field">
                    <label>Konfirmasi Password <span style="color:red">*</span></label>
                    <input class="input" type="password" name="password_confirmation" required minlength="8" placeholder="Ulangi password">
                </div>
                <button class="button" style="width:100%;margin-top:4px">Buat Pengguna</button>
            </form>
        </section>

    </div>

    {{-- ── Change password modals ────────────────────────────── --}}
    @foreach($users as $user)
        <input class="modal-toggle" type="checkbox" id="modal-pwd-{{ $user->id }}">
        <div class="modal">
            <div class="modal-card" style="max-width:400px">
                <div class="modal-head">
                    <h3>Ganti Password — {{ $user->name }}</h3>
                    <label class="button secondary mini" for="modal-pwd-{{ $user->id }}">Tutup</label>
                </div>
                <div class="modal-body">
                    <form method="post" action="{{ route('owner.users.password', $user) }}">
                        @csrf
                        <div class="field">
                            <label>Password Baru <span style="color:red">*</span></label>
                            <input class="input" type="password" name="password" required minlength="8" placeholder="Min. 8 karakter">
                        </div>
                        <div class="field">
                            <label>Konfirmasi Password <span style="color:red">*</span></label>
                            <input class="input" type="password" name="password_confirmation" required minlength="8" placeholder="Ulangi password baru">
                        </div>
                        <button class="button" style="width:100%;margin-top:8px">Simpan Password</button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

</x-layouts.app>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Butik POS' }}</title>
    <link rel="stylesheet" href="{{ asset('css/pos.css') }}?v={{ filemtime(public_path('css/pos.css')) }}">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">Butik POS</div>
        <div class="brand-sub">Retail management</div>
        <div class="role-card">
            <small>Login sebagai</small>
            <strong>{{ auth()->user()->name }}</strong>
            <div class="muted">{{ auth()->user()->isOwner() ? 'Owner / Super Admin' : 'Kasir' }}</div>
        </div>

        <div class="nav-section">Operasional</div>
        <a class="nav-link {{ request()->routeIs('cashier.pos') ? 'active' : '' }}" href="{{ route('cashier.pos') }}">Transaksi Kasir</a>
        <a class="nav-link {{ request()->routeIs('cashier.history') ? 'active' : '' }}" href="{{ route('cashier.history') }}">History Transaksi</a>
        <a class="nav-link {{ request()->routeIs('products.index') ? 'active' : '' }}" href="{{ route('products.index') }}">Input Barang dan Pembelian</a>

        @if(auth()->user()->isOwner())
            <div class="nav-section">Owner</div>
            <a class="nav-link {{ request()->routeIs('owner.dashboard') ? 'active' : '' }}" href="{{ route('owner.dashboard') }}">Dashboard Owner</a>
            <a class="nav-link {{ request()->routeIs('owner.reports') ? 'active' : '' }}" href="{{ route('owner.reports') }}">Laporan Pendapatan</a>
            <a class="nav-link {{ request()->routeIs('owner.settings') ? 'active' : '' }}" href="{{ route('owner.settings') }}">Pengaturan Toko</a>
        @endif

        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="logout-button">Keluar</button>
        </form>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h2>{{ $title ?? 'Butik POS' }}</h2>
                <span>{{ now()->translatedFormat('d F Y') }} · Sistem transaksi butik</span>
            </div>
            <div style="display:flex;align-items:center;gap:12px">
                @if(auth()->user()->isOwner())
                    @php($unreadNotifCount = \App\Models\Notification::query()->whereNull('read_at')->count())
                    @if($unreadNotifCount > 0)
                        <a href="{{ route('owner.dashboard') }}#notifikasi" style="position:relative;text-decoration:none">
                            <span style="background:#ef4444;color:white;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:700">{{ $unreadNotifCount }} notifikasi baru</span>
                        </a>
                    @endif
                @endif
                <span>{{ auth()->user()->isOwner() ? 'Akses penuh' : 'Akses operasional' }}</span>
            </div>
        </header>
        <section class="content">
            @if(session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="error">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            {{ $slot }}
        </section>
    </main>
</div>
<script src="{{ asset('js/pos.js') }}?v={{ filemtime(public_path('js/pos.js')) }}"></script>
<script src="{{ asset('js/pricing.js') }}?v={{ filemtime(public_path('js/pricing.js')) }}"></script>
</body>
</html>

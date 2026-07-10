<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Butik POS' }}</title>
    <link rel="stylesheet" href="{{ asset('css/pos.css') }}?v={{ filemtime(public_path('css/pos.css')) }}">
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<button class="sidebar-reopen-btn" id="sidebarReopenBtn" onclick="toggleSidebarCollapse()" title="Buka sidebar">▶</button>
<div class="app-shell">
    <aside class="sidebar" id="appSidebar">
        <button class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" title="Sembunyikan sidebar">◀</button>
        <div class="brand">Butik POS</div>
        <div class="brand-sub">Retail management</div>
        <div class="role-card">
            <small>Login sebagai</small>
            <strong>{{ auth()->user()->name }}</strong>
            <div class="muted">{{ auth()->user()->isOwner() ? 'Owner / Super Admin' : 'Kasir' }}</div>
        </div>

        @if(auth()->user()->isOwner())
            {{-- OWNER SIDEBAR --}}
            <div class="nav-section">Kasir</div>
            <a class="nav-link {{ request()->routeIs('cashier.pos') ? 'active' : '' }}" href="{{ route('cashier.pos') }}" onclick="closeSidebar()" title="Transaksi Kasir">
                <span class="nav-icon">🛒</span><span class="nav-text"> Transaksi Kasir</span>
            </a>
            <a class="nav-link {{ request()->routeIs('products.index') ? 'active' : '' }}" href="{{ route('products.index') }}" onclick="closeSidebar()" title="Input Barang">
                <span class="nav-icon">📦</span><span class="nav-text"> Input Barang</span>
            </a>

            <div class="nav-section">Owner</div>
            <a class="nav-link {{ request()->routeIs('owner.dashboard') ? 'active' : '' }}" href="{{ route('owner.dashboard') }}" onclick="closeSidebar()" title="Dashboard">
                <span class="nav-icon">📊</span><span class="nav-text"> Dashboard</span>
            </a>
            <a class="nav-link {{ request()->routeIs('owner.history') ? 'active' : '' }}" href="{{ route('owner.history') }}" onclick="closeSidebar()" title="Riwayat Transaksi">
                <span class="nav-icon">🧾</span><span class="nav-text"> Riwayat Transaksi</span>
            </a>
            <a class="nav-link {{ request()->routeIs('owner.reports') ? 'active' : '' }}" href="{{ route('owner.reports') }}" onclick="closeSidebar()" title="Laporan Pendapatan">
                <span class="nav-icon">💰</span><span class="nav-text"> Laporan Pendapatan</span>
            </a>
            <a class="nav-link {{ request()->routeIs('owner.stock-report') ? 'active' : '' }}" href="{{ route('owner.stock-report') }}" onclick="closeSidebar()" title="Laporan Stok">
                <span class="nav-icon">📋</span><span class="nav-text"> Laporan Stok</span>
            </a>

            <div class="nav-section">Manajemen</div>
            <a class="nav-link {{ request()->routeIs('owner.users') ? 'active' : '' }}" href="{{ route('owner.users') }}" onclick="closeSidebar()" title="Kelola Pengguna">
                <span class="nav-icon">👤</span><span class="nav-text"> Kelola Pengguna</span>
            </a>
            <a class="nav-link {{ request()->routeIs('owner.settings') ? 'active' : '' }}" href="{{ route('owner.settings') }}" onclick="closeSidebar()" title="Pengaturan Toko">
                <span class="nav-icon">⚙️</span><span class="nav-text"> Pengaturan Toko</span>
            </a>
        @else
            {{-- CASHIER SIDEBAR --}}
            <div class="nav-section">Operasional</div>
            <a class="nav-link {{ request()->routeIs('cashier.pos') ? 'active' : '' }}" href="{{ route('cashier.pos') }}" onclick="closeSidebar()" title="Transaksi Kasir">
                <span class="nav-icon">🛒</span><span class="nav-text"> Transaksi Kasir</span>
            </a>
            <a class="nav-link {{ request()->routeIs('cashier.history') ? 'active' : '' }}" href="{{ route('cashier.history') }}" onclick="closeSidebar()" title="Riwayat Transaksi">
                <span class="nav-icon">🧾</span><span class="nav-text"> Riwayat Transaksi</span>
            </a>
            <a class="nav-link {{ request()->routeIs('products.index') ? 'active' : '' }}" href="{{ route('products.index') }}" onclick="closeSidebar()" title="Input Barang">
                <span class="nav-icon">📦</span><span class="nav-text"> Input Barang</span>
            </a>
        @endif

        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button class="logout-button" title="Keluar">⬅<span class="nav-text"> Keluar</span></button>
        </form>
    </aside>

    <main class="main">
        <header class="topbar">
            <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Buka menu">
                <div class="hamburger-icon">
                    <span></span><span></span><span></span>
                </div>
            </button>
            <div style="min-width:0;flex:1">
                <h2>{{ $title ?? 'Butik POS' }}</h2>
                <span>{{ now()->translatedFormat('d F Y') }} · Sistem transaksi butik</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
                @if(auth()->user()->isOwner())
                    @php($unreadNotifCount = \App\Models\Notification::query()->whereNull('read_at')->count())
                    @if($unreadNotifCount > 0)
                        <a href="{{ route('owner.dashboard') }}#notifikasi" style="text-decoration:none" title="{{ $unreadNotifCount }} notifikasi belum dibaca">
                            <span class="badge-pulse" style="background:#ef4444;color:white;border-radius:999px;padding:2px 9px;font-size:12px;font-weight:700;white-space:nowrap;display:inline-block">{{ $unreadNotifCount }} notif</span>
                        </a>
                    @endif
                @endif
                <span style="font-size:12px;color:var(--muted);white-space:nowrap">{{ auth()->user()->isOwner() ? 'Owner' : 'Kasir' }}</span>
            </div>
        </header>
        <section class="content">
            @if(session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="error">{{ session('error') }}</div>
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
    {{-- Toast notification container --}}
    <div id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    {{-- Custom confirm modal --}}
    <div id="confirmModal" style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;padding:16px;">
        <div style="background:#fff;border-radius:14px;padding:32px 28px;max-width:360px;width:100%;box-shadow:0 8px 40px rgba(0,0,0,0.18);text-align:center;">
            <div style="width:48px;height:48px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:22px;">🗑</div>
            <div id="confirmModalMsg" style="font-size:15px;font-weight:600;color:#1d242c;margin-bottom:8px;">Hapus item ini?</div>
            <div style="font-size:13px;color:#67727f;margin-bottom:24px;">Tindakan ini tidak dapat dibatalkan.</div>
            <div style="display:flex;gap:10px;justify-content:center;">
                <button onclick="closeConfirmModal()" style="flex:1;padding:10px 0;border:1px solid #e2e8f0;border-radius:8px;background:#fff;font-size:14px;font-weight:600;color:#1d242c;cursor:pointer;">Batal</button>
                <button onclick="doConfirmDelete()" style="flex:1;padding:10px 0;border:0;border-radius:8px;background:#b2472f;color:#fff;font-size:14px;font-weight:600;cursor:pointer;">Ya, Hapus</button>
            </div>
        </div>
    </div>
<script src="{{ asset('js/pos.js') }}?v={{ filemtime(public_path('js/pos.js')) }}"></script>
<script src="{{ asset('js/pricing.js') }}?v={{ filemtime(public_path('js/pricing.js')) }}"></script>
<script>
// ── Sidebar collapse (fully hide / show) ────────────────────
function toggleSidebarCollapse() {
    var shell     = document.querySelector('.app-shell');
    var collapsed = shell.classList.toggle('sidebar-collapsed');
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    try { localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0'); } catch(e) {}
}
document.addEventListener('DOMContentLoaded', function() {
    try {
        if (localStorage.getItem('sidebar_collapsed') === '1') {
            document.querySelector('.app-shell').classList.add('sidebar-collapsed');
            document.body.classList.add('sidebar-collapsed');
        }
    } catch(e) {}
});

// ── Sidebar drawer ──────────────────────────────────────────
function toggleSidebar() {
    var s = document.getElementById('appSidebar');
    var o = document.getElementById('sidebarOverlay');
    var open = s.classList.toggle('open');
    o.classList.toggle('open', open);
    document.body.style.overflow = open ? 'hidden' : '';
}
function closeSidebar() {
    var s = document.getElementById('appSidebar');
    var o = document.getElementById('sidebarOverlay');
    s.classList.remove('open');
    o.classList.remove('open');
    document.body.style.overflow = '';
}
// Close drawer on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
});
// Close drawer on swipe-left gesture
(function() {
    var startX = 0;
    document.addEventListener('touchstart', function(e) { startX = e.touches[0].clientX; }, { passive: true });
    document.addEventListener('touchend', function(e) {
        if (startX < 40 && e.changedTouches[0].clientX < startX - 30) closeSidebar();
    }, { passive: true });
})();
</script>
<style>
#toastContainer {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
    max-width: min(360px, calc(100vw - 32px));
}
.toast {
    background: #1d242c;
    color: white;
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.4;
    box-shadow: 0 8px 32px rgba(16,33,41,.22);
    pointer-events: auto;
    display: flex;
    align-items: flex-start;
    gap: 10px;
    animation: toastIn .25s cubic-bezier(.34,1.56,.64,1) both;
    border-left: 4px solid #177245;
    word-break: break-word;
}
.toast.toast-error { border-left-color: #b42318; }
.toast.toast-warn  { border-left-color: #b86b00; }
.toast.toast-out   { animation: toastOut .2s ease-in both; }
.toast-icon { font-size: 16px; flex-shrink: 0; margin-top: 1px; }
@keyframes toastIn  { from { opacity:0; transform:translateY(12px) scale(.95); } to { opacity:1; transform:none; } }
@keyframes toastOut { from { opacity:1; transform:none; } to { opacity:0; transform:translateY(8px) scale(.95); } }
@media (max-width: 820px) {
    #toastContainer { bottom: 16px; right: 12px; left: 12px; max-width: none; }
}
</style>
<script>
function showToast(msg, type) {
    var container = document.getElementById('toastContainer');
    if (!container) return;
    var t = type || 'success';
    var icons = { success: '✓', error: '✕', warn: '!' };
    var div = document.createElement('div');
    div.className = 'toast' + (t === 'error' ? ' toast-error' : t === 'warn' ? ' toast-warn' : '');
    div.innerHTML = '<span class="toast-icon">' + (icons[t] || '✓') + '</span><span>' + msg + '</span>';
    container.appendChild(div);
    setTimeout(function() {
        div.classList.add('toast-out');
        setTimeout(function() { if (div.parentNode) div.parentNode.removeChild(div); }, 220);
    }, t === 'error' ? 5000 : 3500);
}
// ── Custom confirm modal ─────────────────────────────────────
var _confirmForm = null;
function confirmDelete(form, msg) {
    _confirmForm = form;
    document.getElementById('confirmModalMsg').textContent = msg || 'Hapus item ini?';
    var modal = document.getElementById('confirmModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    return false;
}
function doConfirmDelete() {
    if (_confirmForm) {
        var f = _confirmForm;
        closeConfirmModal();
        // Use a hidden submit button to bypass onsubmit
        var btn = document.createElement('input');
        btn.type = 'submit';
        btn.style.display = 'none';
        f.appendChild(btn);
        f.onsubmit = null;
        btn.click();
    }
}
function closeConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
    document.body.style.overflow = '';
    _confirmForm = null;
}
document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfirmModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConfirmModal();
});

// Auto-show toasts from Laravel session flash
@if(session('status'))
    document.addEventListener('DOMContentLoaded', function() {
        showToast({{ Js::from(session('status')) }});
    });
@endif
@if(session('error'))
    document.addEventListener('DOMContentLoaded', function() {
        showToast({{ Js::from(session('error')) }}, 'error');
    });
@endif
@if($errors->any())
    document.addEventListener('DOMContentLoaded', function() {
        showToast({{ Js::from($errors->first()) }}, 'error');
    });
@endif
</script>
</body>
</html>

<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · Butik POS</title>
    <link rel="stylesheet" href="{{ asset('css/pos.css') }}?v=20260625">
    <style>
        .login-page { min-height:100vh; display:flex; align-items:center; justify-content:center; background:#f4f6f8; padding:16px; }
        .login-shell { display:block !important; grid-template-columns:none !important; width:100%; max-width:400px; }
        .login-card { background:#fff; border-radius:16px; padding:36px 32px; box-shadow:0 4px 24px rgba(0,0,0,.08); }
        .login-logo { text-align:center; margin-bottom:28px; }
        .login-logo h1 { font-size:1.6rem; font-weight:700; color:#1d242c; margin:0 0 4px; }
        .login-logo p { color:#6b7280; font-size:.875rem; margin:0; }
        .field { margin-bottom:16px; }
        .field label { display:block; font-size:.875rem; font-weight:500; color:#374151; margin-bottom:6px; }
        .input { width:100%; padding:10px 14px; border:1.5px solid #d1d5db; border-radius:8px; font-size:.95rem; transition:border-color .15s, box-shadow .15s; box-sizing:border-box; background:#fff; }
        .input:focus { outline:none; border-color:#177245; box-shadow:0 0 0 3px rgba(23,114,69,.12); }
        .remember-row { display:flex; align-items:center; margin-bottom:20px; }
        .remember-label { display:flex; align-items:center; gap:8px; font-size:.875rem; color:#374151; cursor:pointer; user-select:none; }
        .remember-label input[type=checkbox] { width:16px; height:16px; accent-color:#177245; cursor:pointer; flex-shrink:0; }
        .btn-login { width:100%; padding:11px; background:#177245; color:#fff; border:none; border-radius:8px; font-size:1rem; font-weight:600; cursor:pointer; transition:background .15s, transform .1s, opacity .15s; letter-spacing:.01em; }
        .btn-login:hover:not(:disabled) { background:#135d38; }
        .btn-login:active:not(:disabled) { transform:scale(.98); }
        .btn-login:disabled { opacity:.65; cursor:not-allowed; }
        .btn-login .spinner { display:inline-block; width:15px; height:15px; border:2px solid rgba(255,255,255,.35); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; vertical-align:middle; margin-right:6px; margin-bottom:1px; }
        @keyframes spin { to { transform:rotate(360deg) } }
        .error-box { background:#fef2f2; border:1px solid #fca5a5; color:#b91c1c; padding:10px 14px; border-radius:8px; font-size:.875rem; margin-bottom:16px; }
        .pw-wrapper { position:relative; }
        .pw-toggle { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#9ca3af; font-size:.85rem; padding:4px; line-height:1; }
        .pw-toggle:hover { color:#374151; }
        @media(max-width:480px) { .login-card { padding:28px 20px; border-radius:12px; } }
    </style>
</head>
<body class="login-page">
<div class="login-shell">
    <div class="login-card">
        <div class="login-logo">
            <h1>Butik POS</h1>
            <p>Sistem kasir &amp; manajemen butik</p>
        </div>

        @if($errors->any())
            <div class="error-box">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('login.store') }}" id="loginForm" autocomplete="on">
            @csrf
            <div class="field">
                <label for="email">Email</label>
                <input
                    class="input"
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@butik.com"
                    inputmode="email"
                >
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="pw-wrapper">
                    <input
                        class="input"
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        style="padding-right:44px"
                    >
                    <button type="button" class="pw-toggle" onclick="togglePw()" id="pwToggle" tabindex="-1" aria-label="Tampilkan password">Lihat</button>
                </div>
            </div>

            <div class="remember-row">
                <label class="remember-label">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    Ingat Saya (tetap login)
                </label>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">Masuk</button>
        </form>
    </div>
</div>

<div id="toastContainer" aria-live="polite"></div>
<style>
#toastContainer { position:fixed; bottom:20px; right:20px; z-index:9999; display:flex; flex-direction:column; gap:8px; pointer-events:none; max-width:min(340px,calc(100vw - 32px)); }
.toast { background:#1d242c; color:white; padding:12px 16px; border-radius:10px; font-size:14px; line-height:1.4; box-shadow:0 8px 32px rgba(16,33,41,.22); pointer-events:auto; display:flex; align-items:flex-start; gap:10px; animation:toastIn .25s cubic-bezier(.34,1.56,.64,1) both; border-left:4px solid #177245; }
.toast.toast-error { border-left-color:#b42318; }
.toast.toast-out { animation:toastOut .2s ease-in both; }
@keyframes toastIn  { from{opacity:0;transform:translateY(12px) scale(.95)} to{opacity:1;transform:none} }
@keyframes toastOut { from{opacity:1;transform:none} to{opacity:0;transform:translateY(8px) scale(.95)} }
</style>
<script>
function showToast(msg, type) {
    var c = document.getElementById('toastContainer');
    var d = document.createElement('div');
    d.className = 'toast' + (type === 'error' ? ' toast-error' : '');
    d.innerHTML = '<span>' + msg + '</span>';
    c.appendChild(d);
    setTimeout(function(){ d.classList.add('toast-out'); setTimeout(function(){ d.parentNode && d.parentNode.removeChild(d); }, 220); }, type === 'error' ? 5000 : 3000);
}

function togglePw() {
    var pw = document.getElementById('password');
    var btn = document.getElementById('pwToggle');
    if (pw.type === 'password') { pw.type = 'text'; btn.textContent = 'Sembunyikan'; }
    else { pw.type = 'password'; btn.textContent = 'Lihat'; }
}

document.getElementById('loginForm').addEventListener('submit', function() {
    var btn = document.getElementById('loginBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Masuk...';
});

@if($errors->any())
document.addEventListener('DOMContentLoaded', function(){ showToast({{ Js::from($errors->first()) }}, 'error'); });
@endif
</script>
</body>
</html>

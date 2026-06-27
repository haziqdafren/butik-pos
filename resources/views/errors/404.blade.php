<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 – Halaman Tidak Ditemukan · Butik POS</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            color: #222;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 48px 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,.08);
        }
        .code {
            font-size: 80px;
            font-weight: 800;
            color: #b2472f;
            line-height: 1;
            letter-spacing: -4px;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            margin: 16px 0 8px;
            color: #1d242c;
        }
        .desc {
            font-size: 14px;
            color: #67727f;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .btn {
            display: inline-block;
            background: #b2472f;
            color: white;
            text-decoration: none;
            padding: 12px 28px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
        }
        .btn:hover { background: #943a26; }
        .back-link {
            display: block;
            margin-top: 16px;
            font-size: 13px;
            color: #67727f;
            text-decoration: none;
        }
        .back-link:hover { color: #1d242c; }
    </style>
</head>
<body>
<div class="card">
    <div class="code">404</div>
    <div class="title">Halaman Tidak Ditemukan</div>
    <p class="desc">Halaman yang Anda cari tidak ada atau telah dipindahkan.<br>Silakan kembali ke halaman utama.</p>
    @auth
        @if(auth()->user()->isOwner())
            <a href="{{ route('owner.dashboard') }}" class="btn">Ke Dashboard Owner</a>
        @else
            <a href="{{ route('cashier.pos') }}" class="btn">Ke Halaman Kasir</a>
        @endif
    @else
        <a href="{{ route('login') }}" class="btn">Ke Halaman Login</a>
    @endauth
    <a href="javascript:history.back()" class="back-link">← Kembali ke halaman sebelumnya</a>
</div>
</body>
</html>

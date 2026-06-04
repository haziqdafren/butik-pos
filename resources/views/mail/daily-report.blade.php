<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px; }
        h1 { color: #1e3a5f; border-bottom: 2px solid #e5e7eb; padding-bottom: 8px; }
        h2 { color: #374151; font-size: 16px; margin-top: 24px; margin-bottom: 8px; }
        .box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px; margin-bottom: 16px; }
        .row { display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #f3f4f6; }
        .row:last-child { border-bottom: none; }
        .label { color: #6b7280; }
        .value { font-weight: bold; }
        .void-item { background: #fef2f2; border-left: 4px solid #ef4444; padding: 8px 12px; margin: 6px 0; border-radius: 0 4px 4px 0; }
        .stock-item { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 8px 12px; margin: 6px 0; border-radius: 0 4px 4px 0; }
        .ok { color: #059669; font-weight: bold; }
        .footer { color: #9ca3af; font-size: 12px; margin-top: 32px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
    </style>
</head>
<body>
    <h1>Laporan Harian Butik</h1>
    <p>Halo <strong>{{ $ownerName }}</strong>,</p>
    <p>Berikut ringkasan kegiatan toko hari ini, <strong>{{ $date }}</strong>:</p>

    <h2>Penjualan Hari Ini</h2>
    <div class="box">
        @if($totalTransactions === 0)
            <p style="color:#6b7280;margin:0">Tidak ada transaksi hari ini.</p>
        @else
            <div class="row">
                <span class="label">Total Transaksi</span>
                <span class="value">{{ $totalTransactions }} kali</span>
            </div>
            <div class="row">
                <span class="label">Pemasukan</span>
                <span class="value">Rp {{ number_format($revenue, 0, ',', '.') }}</span>
            </div>
            <div class="row">
                <span class="label">Keuntungan Bersih</span>
                <span class="value">Rp {{ number_format($profit, 0, ',', '.') }}</span>
            </div>
        @endif
    </div>

    <h2>Pembatalan Transaksi Hari Ini</h2>
    @if(count($voids) === 0)
        <p class="ok">Tidak ada transaksi yang dibatalkan hari ini.</p>
    @else
        @foreach($voids as $void)
            <div class="void-item">
                <strong>{{ $void['invoice'] }}</strong> dibatalkan oleh {{ $void['cashier'] }}<br>
                <span style="color:#6b7280">Alasan: {{ $void['reason'] }}</span>
            </div>
        @endforeach
    @endif

    <h2>Barang Stok Menipis</h2>
    @if(count($lowStocks) === 0)
        <p class="ok">Semua stok masih aman. Tidak perlu restock hari ini.</p>
    @else
        @foreach($lowStocks as $item)
            <div class="stock-item">
                <strong>{{ $item['name'] }}</strong> — sisa <strong>{{ $item['stock'] }} pcs</strong><br>
                <span style="color:#6b7280">Supplier: {{ $item['supplier'] ?: 'belum diisi' }}</span>
            </div>
        @endforeach
        <p style="color:#92400e">Segera lakukan restock melalui menu <em>Dashboard Owner → Restock Barang</em>.</p>
    @endif

    <div class="footer">
        <p>Email ini dikirim otomatis setiap hari pukul 20:00 oleh Sistem Butik POS.<br>
        Jika ada pertanyaan, hubungi admin toko Anda.</p>
    </div>
</body>
</html>

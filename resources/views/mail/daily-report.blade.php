<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laporan Harian &mdash; {{ $date }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f2;font-family:Arial,sans-serif;font-size:14px;color:#1d242c;">

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f2;padding:32px 16px;">
  <tr>
    <td align="center">
      <table width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.07);">

        {{-- Header --}}
        <tr>
          <td style="background:#b2472f;padding:28px 32px;border-radius:12px 12px 0 0;">
            <div style="font-size:20px;font-weight:700;color:#ffffff;margin-bottom:4px;">{{ $storeName }}</div>
            <div style="font-size:13px;color:rgba(255,255,255,0.82);">Laporan Harian &mdash; {{ $date }}</div>
          </td>
        </tr>

        {{-- Body --}}
        <tr>
          <td style="padding:28px 32px;">

            <p style="margin:0 0 24px;font-size:15px;">Halo <strong>{{ $ownerName }}</strong>, berikut rekap transaksi hari ini.</p>

            {{-- ── Section 1: Ringkasan ── --}}
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#67727f;border-bottom:2px solid #b2472f;padding-bottom:6px;margin-bottom:14px;">
              Ringkasan Hari Ini
            </div>

            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px;">
              <tr>
                <td style="background:#fdf6f4;border:1px solid #ecddd9;border-radius:8px;padding:14px 12px;text-align:center;">
                  <div style="font-size:11px;color:#67727f;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Transaksi</div>
                  <div style="font-size:26px;font-weight:700;color:#b2472f;">{{ $totalTransactions }}</div>
                </td>
                <td width="10"></td>
                <td style="background:#fdf6f4;border:1px solid #ecddd9;border-radius:8px;padding:14px 12px;text-align:center;">
                  <div style="font-size:11px;color:#67727f;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Pendapatan</div>
                  <div style="font-size:15px;font-weight:700;color:#b2472f;">Rp {{ number_format($revenue, 0, ',', '.') }}</div>
                </td>
                <td width="10"></td>
                <td style="background:#fdf6f4;border:1px solid #ecddd9;border-radius:8px;padding:14px 12px;text-align:center;">
                  <div style="font-size:11px;color:#67727f;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Profit</div>
                  <div style="font-size:15px;font-weight:700;color:{{ $profit >= 0 ? '#b2472f' : '#b45309' }};">Rp {{ number_format($profit, 0, ',', '.') }}</div>
                </td>
              </tr>
            </table>

            @if($totalTransactions === 0)
              <p style="color:#67727f;font-size:13px;text-align:center;padding:4px 0 20px;">Tidak ada transaksi hari ini.</p>
            @endif

            {{-- ── Section 2: Penjualan per Toko ── --}}
            @foreach($storeBreakdowns as $store)
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#67727f;border-bottom:2px solid #b2472f;padding-bottom:6px;margin-bottom:14px;">
              {{ $store['store_name'] }} — Penjualan Hari Ini
            </div>

            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:14px;">
              <tr>
                <td style="background:#fdf6f4;border:1px solid #ecddd9;border-radius:8px;padding:10px 12px;text-align:center;">
                  <div style="font-size:11px;color:#67727f;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Transaksi</div>
                  <div style="font-size:22px;font-weight:700;color:#b2472f;">{{ $store['transactions'] }}</div>
                </td>
                <td width="10"></td>
                <td style="background:#fdf6f4;border:1px solid #ecddd9;border-radius:8px;padding:10px 12px;text-align:center;">
                  <div style="font-size:11px;color:#67727f;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Pendapatan</div>
                  <div style="font-size:14px;font-weight:700;color:#b2472f;">Rp {{ number_format($store['revenue'], 0, ',', '.') }}</div>
                </td>
              </tr>
            </table>

            @if(count($store['rows']) === 0)
              <p style="color:#67727f;font-size:13px;margin:0 0 24px;">Tidak ada penjualan dari {{ $store['store_name'] }} hari ini.</p>
            @else
              <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:28px;border-collapse:collapse;">
                <thead>
                  <tr style="background:#fdf6f4;">
                    <td style="padding:8px 12px;font-size:11px;font-weight:700;color:#1d242c;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1px solid #ecddd9;">Kategori</td>
                    <td style="padding:8px 12px;font-size:11px;font-weight:700;color:#1d242c;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1px solid #ecddd9;text-align:center;">Terjual</td>
                    <td style="padding:8px 12px;font-size:11px;font-weight:700;color:#1d242c;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1px solid #ecddd9;text-align:right;">Pendapatan</td>
                  </tr>
                </thead>
                <tbody>
                  @foreach($store['rows'] as $i => $row)
                  <tr style="background:{{ $i % 2 === 0 ? '#ffffff' : '#fdf6f4' }};">
                    <td style="padding:9px 12px;font-size:13px;color:#1d242c;border-bottom:1px solid #f3f0ee;">{{ $row['category'] }}</td>
                    <td style="padding:9px 12px;font-size:13px;font-weight:700;color:#b2472f;text-align:center;border-bottom:1px solid #f3f0ee;">{{ $row['qty'] }} pcs</td>
                    <td style="padding:9px 12px;font-size:13px;color:#1d242c;text-align:right;border-bottom:1px solid #f3f0ee;">Rp {{ number_format($row['revenue'], 0, ',', '.') }}</td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            @endif
            @endforeach

            {{-- ── Section 3: Transaksi Dibatalkan ── --}}
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#67727f;border-bottom:2px solid #e5e7eb;padding-bottom:6px;margin-bottom:14px;">
              Transaksi Dibatalkan ({{ $totalVoids }})
            </div>

            @if($totalVoids === 0)
              <p style="color:#b2472f;font-weight:700;margin:0 0 24px;font-size:13px;">Tidak ada transaksi yang dibatalkan hari ini.</p>
            @else
              @foreach($voids as $void)
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:6px;">
                  <tr>
                    <td style="background:#fef2f2;border-left:4px solid #ef4444;border-radius:0 4px 4px 0;padding:8px 12px;font-size:13px;">
                      <strong>{{ $void['invoice'] }}</strong> &mdash; {{ $void['cashier'] }}<br>
                      <span style="color:#67727f;">Alasan: {{ $void['reason'] }}</span>
                    </td>
                  </tr>
                </table>
              @endforeach
              @if($totalVoids > count($voids))
                <p style="color:#67727f;font-size:12px;margin:6px 0 20px;">... dan {{ $totalVoids - count($voids) }} pembatalan lainnya. Lihat detail di sistem.</p>
              @else
                <div style="height:20px;"></div>
              @endif
            @endif

            {{-- ── Section 4: Stok Perlu Perhatian ── --}}
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#67727f;border-bottom:2px solid #e5e7eb;padding-bottom:6px;margin-bottom:14px;">
              Stok Perlu Perhatian ({{ $totalLowStocks }})
            </div>

            @if($totalLowStocks === 0)
              <p style="color:#b2472f;font-weight:700;margin:0 0 8px;font-size:13px;">Semua stok masih aman.</p>
            @else
              @foreach($lowStocks as $item)
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:6px;">
                  <tr>
                    <td style="background:#fffbeb;border-left:4px solid #f59e0b;border-radius:0 4px 4px 0;padding:8px 12px;font-size:13px;">
                      <strong>{{ $item['name'] }}</strong>
                      &mdash;
                      @if($item['stock'] === 0)
                        <span style="background:#fee2e2;color:#991b1b;border-radius:4px;padding:1px 7px;font-size:11px;font-weight:700;">HABIS</span>
                      @else
                        <span style="background:#fef3c7;color:#92400e;border-radius:4px;padding:1px 7px;font-size:11px;font-weight:700;">{{ $item['stock'] }} pcs</span>
                      @endif
                      <br>
                      <span style="color:#67727f;">Supplier: {{ $item['supplier'] ?: 'belum diisi' }}</span>
                    </td>
                  </tr>
                </table>
              @endforeach
              @if($totalLowStocks > count($lowStocks))
                <p style="color:#67727f;font-size:12px;margin:6px 0 4px;">... dan {{ $totalLowStocks - count($lowStocks) }} barang lainnya. Cek Laporan Stok di sistem.</p>
              @endif
              <p style="color:#92400e;font-size:13px;margin:10px 0 0;">Lakukan restock melalui Dashboard Owner &rarr; Restock Barang.</p>
            @endif

          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="background:#fdf6f4;border-top:1px solid #ecddd9;padding:20px 32px;font-size:12px;color:#9ca3af;text-align:center;border-radius:0 0 12px 12px;">
            Untuk detail lengkap transaksi, kunjungi sistem di
            <a href="{{ config('app.url') }}" style="color:#b2472f;text-decoration:none;">{{ config('app.url') }}</a><br><br>
            Email ini dikirim otomatis setiap hari pukul 20:00 oleh sistem {{ $storeName }}. Jangan balas email ini.
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>

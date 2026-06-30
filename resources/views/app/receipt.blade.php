<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $sale->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            line-height: 1.5;
            background: #e8e8e8;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 16px;
            min-height: 100vh;
        }

        /* Screen preview — matches 48mm printable area at 96dpi */
        .receipt {
            background: white;
            width: 181px; /* 48mm @ 96dpi */
            padding: 8px 4px;
            border: 1px solid #bbb;
            box-shadow: 0 2px 8px rgba(0,0,0,.12);
        }

        .center  { text-align: center; }
        .bold    { font-weight: bold; }
        .right   { text-align: right; }
        .muted   { color: #555; }

        .divider       { border: none; border-top: 1px dashed #888; margin: 3px 0; }
        .divider-solid { border: none; border-top: 1px solid #222; margin: 3px 0; }

        /* Two-column row */
        .row { display: flex; justify-content: space-between; margin: 1px 0; }
        .row .label { white-space: nowrap; margin-right: 4px; flex-shrink: 0; }
        .row .value { text-align: right; word-break: break-word; }

        /* Item table */
        .items-table { width: 100%; border-collapse: collapse; margin: 2px 0; }
        .items-table td { vertical-align: top; padding: 1px 0; font-size: 14px; }
        .col-name  { width: 52%; }
        .col-qty   { width: 13%; text-align: center; }
        .col-price { width: 35%; text-align: right; }

        .item-attrs { color: #666; font-size: 12px; }

        .total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 15px; margin: 2px 0; }

        .footer-msg { text-align: center; margin-top: 6px; font-size: 13px; }

        /* Screen buttons */
        .no-print { margin-top: 16px; display: flex; gap: 8px; }
        .btn { padding: 8px 16px; border: 0; border-radius: 6px; cursor: pointer; font-family: inherit; font-size: 13px; }
        .btn-print { background: #b2472f; color: white; }
        .btn-close { background: #67727f; color: white; }

        /* ── Print: 58mm thermal roll (Blueprint Lite 58D) ── */
        @page {
            size: 58mm auto;
            margin: 0;
        }

        @media print {
            html, body {
                width: 58mm;
                margin: 0;
                padding: 0;
            }

            .receipt {
                width: 58mm;
                padding: 2mm;
                box-sizing: border-box;
                font-family: "Courier New", monospace;
                font-size: 11px;
                border: none;
                box-shadow: none;
            }

            .receipt * { font-weight: bold !important; }

            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="receipt">
    {{-- Header --}}
    <div class="center bold" style="font-size:16px; margin-bottom:2px">
        {{ $sale->store?->name ?: ($settings['store_name'] ?: config('app.name', 'Butik POS')) }}
    </div>
    @php $storeAddress = $sale->store?->address ?: $settings['store_address']; @endphp
    @if(!empty($storeAddress))
        <div class="center muted" style="font-size:10px">{{ $storeAddress }}</div>
    @endif
    @if(!empty($settings['store_phone']))
        <div class="center muted" style="font-size:10px">{{ $settings['store_phone'] }}</div>
    @endif

    <hr class="divider-solid">

    {{-- Transaction meta --}}
    <div class="row">
        <span class="label">Kasir</span>
        <span class="value">{{ $sale->cashier?->name ?? 'Kasir (dihapus)' }}</span>
    </div>
    <div class="row">
        <span class="label">Tanggal</span>
        <span class="value">{{ $sale->created_at->format('d/m/Y H:i') }}</span>
    </div>
    <div class="row">
        <span class="label">Invoice</span>
        <span class="value" style="font-size:10px">{{ $sale->invoice_number }}</span>
    </div>

    <hr class="divider">

    {{-- Items --}}
    <table class="items-table">
        @foreach($sale->items as $item)
            <tr>
                <td class="col-name">
                    <strong>{{ $item->name }}</strong>
                    @php
                        $attrs = array_filter([
                            $item->product?->color ?? null,
                            $item->product?->size  ?? null,
                        ]);
                    @endphp
                    @if($attrs)
                        <div class="item-attrs">{{ implode(' · ', $attrs) }}</div>
                    @endif
                </td>
                <td class="col-qty">{{ $item->qty }}x</td>
                <td class="col-price">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
            </tr>
            @if($item->qty > 1)
                <tr>
                    <td class="col-name"></td>
                    <td class="col-qty"></td>
                    <td class="col-price muted">={{ number_format($item->line_total, 0, ',', '.') }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    <hr class="divider">

    {{-- Subtotal / Diskon / Total --}}
    <div class="row">
        <span>Subtotal</span>
        <span>{{ number_format($sale->subtotal, 0, ',', '.') }}</span>
    </div>
    @if($sale->discount_amount > 0)
        <div class="row">
            <span>Diskon</span>
            <span>- {{ number_format($sale->discount_amount, 0, ',', '.') }}</span>
        </div>
    @endif
    <hr class="divider">
    <div class="total-row">
        <span>TOTAL</span>
        <span>Rp {{ number_format($sale->total, 0, ',', '.') }}</span>
    </div>

    <hr class="divider-solid">

    {{-- Payment --}}
    <div class="row">
        <span>{{ $sale->payment_method }}</span>
        <span>{{ number_format($sale->amount_paid, 0, ',', '.') }}</span>
    </div>
    <div class="row">
        <span>Kembalian</span>
        <span>{{ number_format($sale->change, 0, ',', '.') }}</span>
    </div>

    <hr class="divider-solid">

    {{-- Footer --}}
    <div class="footer-msg">
        {{ $settings['receipt_footer'] ?: 'Terima kasih telah berbelanja!' }}
    </div>
</div>

<div class="no-print">
    <button class="btn btn-print" onclick="window.print()">Cetak Struk</button>
    <button class="btn btn-close" id="btnClose" onclick="window.close()">Tutup</button>
</div>
<script>
    // Hide close button on mobile — window.close() is blocked by iOS/Android browsers
    if (/Mobi|Android|iPhone|iPad/i.test(navigator.userAgent)) {
        var bc = document.getElementById('btnClose');
        if (bc) bc.style.display = 'none';
    }
</script>

<script>
    var autoPrint = {{ $autoPrint ? 'true' : 'false' }};

    if (autoPrint) {
        var mediaQueryList = window.matchMedia('print');
        var hasPrinted = false;

        mediaQueryList.addEventListener('change', function (mql) {
            if (!mql.matches && hasPrinted) {
                window.close();
            }
        });

        window.addEventListener('load', function () {
            setTimeout(function () {
                hasPrinted = true;
                window.print();
            }, 350);
        });
    }
</script>

</body>
</html>

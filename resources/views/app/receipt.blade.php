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
            font-size: 11px;
            line-height: 1.4;
            background: #f0f0f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 16px;
            min-height: 100vh;
        }

        .receipt {
            background: white;
            width: 220px;
            padding: 10px 8px;
            border: 1px solid #ccc;
        }

        .center  { text-align: center; }
        .bold    { font-weight: bold; }
        .right   { text-align: right; }
        .muted   { color: #555; }

        .divider       { border: none; border-top: 1px dashed #aaa; margin: 5px 0; }
        .divider-solid { border: none; border-top: 1px solid #333; margin: 5px 0; }

        .row { display: flex; justify-content: space-between; margin: 2px 0; }
        .row .label { white-space: nowrap; margin-right: 4px; }
        .row .value { text-align: right; }

        .item-name   { font-weight: bold; margin-top: 4px; margin-bottom: 1px; }
        .item-detail { display: flex; justify-content: space-between; }
        .item-sub    { text-align: right; color: #444; }

        .total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 12px; margin: 2px 0; }

        .footer-msg { text-align: center; margin-top: 6px; font-size: 10px; }

        .no-print {
            margin-top: 16px;
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 8px 16px;
            border: 0;
            border-radius: 6px;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
        }
        .btn-print { background: #b2472f; color: white; }
        .btn-close { background: #67727f; color: white; }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
                display: block;
            }
            .receipt {
                border: none;
                width: 100%;
                padding: 2mm 0;
            }
            .no-print { display: none !important; }
            @page {
                size: 58mm auto;
                margin: 3mm 2mm;
            }
        }
    </style>
</head>
<body>

<div class="receipt">
    {{-- Header --}}
    <div class="center bold" style="font-size:13px; margin-bottom:2px">
        {{ $settings['store_name'] ?: config('app.name', 'Butik POS') }}
    </div>
    @if(!empty($settings['store_address']))
        <div class="center muted" style="font-size:10px">{{ $settings['store_address'] }}</div>
    @endif
    @if(!empty($settings['store_phone']))
        <div class="center muted" style="font-size:10px">{{ $settings['store_phone'] }}</div>
    @endif

    <hr class="divider-solid">

    {{-- Transaction meta --}}
    <div class="row">
        <span class="label">Kasir</span>
        <span class="value">{{ $sale->cashier->name }}</span>
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
    @foreach($sale->items as $item)
        <div class="item-name">{{ $item->name }}</div>
        <div class="item-detail muted">
            <span>
                @php
                    $attrs = array_filter([
                        $item->product->color ?? null,
                        $item->product->size  ?? null,
                    ]);
                @endphp
                {{ implode(' · ', $attrs) }}
            </span>
            <span>{{ $item->qty }}x &nbsp;Rp {{ number_format($item->unit_price, 0, ',', '.') }}</span>
        </div>
        @if($item->qty > 1)
            <div class="item-sub">Rp {{ number_format($item->line_total, 0, ',', '.') }}</div>
        @endif
    @endforeach

    <hr class="divider">

    {{-- Subtotal / Diskon / Total --}}
    <div class="row">
        <span>Subtotal</span>
        <span>Rp {{ number_format($sale->subtotal, 0, ',', '.') }}</span>
    </div>
    @if($sale->discount_amount > 0)
        <div class="row">
            <span>Diskon</span>
            <span>- Rp {{ number_format($sale->discount_amount, 0, ',', '.') }}</span>
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
        <span>Rp {{ number_format($sale->amount_paid, 0, ',', '.') }}</span>
    </div>
    <div class="row">
        <span>Kembalian</span>
        <span>Rp {{ number_format($sale->change, 0, ',', '.') }}</span>
    </div>

    <hr class="divider-solid">

    {{-- Footer --}}
    <div class="footer-msg">
        {{ $settings['receipt_footer'] ?: 'Terima kasih telah berbelanja!' }}
    </div>
</div>

<div class="no-print">
    <button class="btn btn-print" onclick="window.print()">Cetak Struk</button>
    <button class="btn btn-close" onclick="window.close()">Tutup</button>
</div>

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

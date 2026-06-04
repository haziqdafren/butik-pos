<x-layouts.app title="Dashboard Owner">
    <div class="grid-4">
        <div class="card metric hot"><small>Total Pendapatan</small><strong>Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</strong></div>
        <div class="card metric good"><small>Profit Bersih</small><strong>Rp {{ number_format($summary['profit'], 0, ',', '.') }}</strong></div>
        <div class="card metric warn"><small>Diskon Diberikan</small><strong>Rp {{ number_format($summary['discounts'], 0, ',', '.') }}</strong></div>
        <div class="card metric info"><small>Transaksi</small><strong>{{ $summary['transactions'] }}</strong></div>
    </div>

    <div class="grid-2" style="margin-top:16px">
        <div class="card">
            <h3>Tren Pendapatan</h3>
            @php($max = max(1, $sales->groupBy(fn($sale) => $sale->created_at->format('d M'))->map->sum('total')->max() ?? 1))
            <div class="chart">
                @foreach($sales->groupBy(fn($sale) => $sale->created_at->format('d M'))->take(7) as $label => $items)
                    <div class="bar" style="height: {{ max(18, ($items->sum('total') / $max) * 140) }}px"><span>{{ $label }}</span></div>
                @endforeach
            </div>
        </div>
        <div class="card">
            <h3>Catatan Diskon</h3>
            @forelse($recentDiscounts as $discount)
                <div class="toolbar" style="justify-content:space-between">
                    <div>
                        <strong>#{{ $discount->id }} · Rp {{ number_format($discount->amount, 0, ',', '.') }}</strong>
                        <div class="muted">{{ $discount->requester->name }} · {{ $discount->reason }}</div>
                    </div>
                    <span class="badge amber">{{ $discount->type === 'percent' ? $discount->value.'%' : 'nominal' }}</span>
                </div>
            @empty
                <p class="muted">Belum ada diskon tercatat.</p>
            @endforelse
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <div class="toolbar" style="justify-content:space-between;align-items:center">
            <h3 style="margin:0">Notifikasi @if($unreadCount > 0)<span class="badge red">{{ $unreadCount }}</span>@endif</h3>
            @if($unreadCount > 0)
                <form method="post" action="{{ route('owner.notifications.read-all') }}">
                    @csrf
                    <button class="button secondary mini">Tandai Semua Dibaca</button>
                </form>
            @endif
        </div>
        @forelse($notifications as $notification)
            <div class="toolbar" style="justify-content:space-between;margin-top:8px">
                <div>
                    <strong>{{ $notification->title }}</strong>
                    <div class="muted">{{ $notification->body }}</div>
                </div>
                <span class="badge {{ $notification->type === 'low_stock' ? 'amber' : 'info' }}">{{ $notification->type }}</span>
            </div>
        @empty
            <p class="muted" style="margin-top:8px">Tidak ada notifikasi baru.</p>
        @endforelse
    </div>

    <div class="grid-2" style="margin-top:16px">
        <div class="card">
            <h3>Transaksi Terbaru</h3>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Invoice</th><th>Kasir</th><th>Total</th><th>Profit</th><th>Metode</th></tr></thead>
                    <tbody>
                    @forelse($sales->take(8) as $sale)
                        <tr>
                            <td>{{ $sale->invoice_number }}</td>
                            <td>{{ $sale->cashier->name }}</td>
                            <td class="money">Rp {{ number_format($sale->total, 0, ',', '.') }}</td>
                            <td class="money">Rp {{ number_format($sale->profit, 0, ',', '.') }}</td>
                            <td><span class="badge">{{ $sale->payment_method }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="muted">Belum ada transaksi.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <h3>Stok Perlu Perhatian</h3>
            @forelse($lowStock as $product)
                <div class="toolbar" style="justify-content:space-between">
                    <div>
                        <strong>{{ $product->name }}</strong>
                        <div class="muted">{{ $product->sku }} · {{ $product->store->name }}</div>
                    </div>
                    <span class="badge {{ $product->stock <= 2 ? 'red' : 'amber' }}">{{ $product->stock }} pcs</span>
                </div>
            @empty
                <p class="muted">Semua stok masih aman.</p>
            @endforelse
        </div>
    </div>
</x-layouts.app>

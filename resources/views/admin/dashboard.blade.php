<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Wallet Admin Backend</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f6fb; color: #1a1f36; }
        .container { max-width: 1280px; margin: 0 auto; padding: 20px; }
        .title { margin: 0 0 14px 0; font-size: 28px; }
        .sub { margin: 0 0 18px 0; color: #4a5572; }
        .grid { display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 12px; margin-bottom: 16px; }
        .card { background: #fff; border-radius: 10px; padding: 14px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .label { color: #637087; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .value { font-size: 24px; margin-top: 8px; font-weight: 700; }
        .panel { background: #fff; border-radius: 10px; padding: 14px; box-shadow: 0 2px 8px rgba(0,0,0,.08); margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 9px 8px; text-align: left; border-bottom: 1px solid #edf1f7; font-size: 14px; }
        th { color: #5c6c87; font-weight: 700; font-size: 12px; text-transform: uppercase; }
        .muted { color: #6a7892; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .ok { color: #008a4b; font-weight: 700; }
        .warn { color: #9a5b00; font-weight: 700; }
        .pill { background: #eef4ff; color: #2958c7; border-radius: 999px; padding: 3px 10px; font-size: 12px; display: inline-block; }
        @media (max-width: 992px) { .grid, .row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="container">
    <h1 class="title">Admin Backend Dashboard</h1>
    <p class="sub">Main wallet + distributor/retailer monitoring and commission tracking</p>

    <div class="grid">
        <div class="card">
            <div class="label">Admin Main Wallet</div>
            <div class="value">₹{{ number_format($stats['admin_main_wallet_balance'], 2) }}</div>
            <div class="muted">{{ $admin?->email ?? 'No admin user found' }}</div>
        </div>
        <div class="card">
            <div class="label">Total Wallet Balance</div>
            <div class="value">₹{{ number_format($stats['total_wallet_balance'], 2) }}</div>
        </div>
        <div class="card">
            <div class="label">Total Commission Paid</div>
            <div class="value">₹{{ number_format($stats['total_commission_paid'], 2) }}</div>
        </div>
        <div class="card">
            <div class="label">Total Distributors</div>
            <div class="value">{{ $stats['total_distributors'] }}</div>
        </div>
        <div class="card">
            <div class="label">Total Retailers</div>
            <div class="value">{{ $stats['total_retailers'] }}</div>
        </div>
        <div class="card">
            <div class="label">Total Withdrawals</div>
            <div class="value">₹{{ number_format($stats['total_withdrawals'], 2) }}</div>
        </div>
    </div>

    <div class="row">
        <div class="panel">
            <h3>Distributors</h3>
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Wallet Balance</th>
                    <th>Retailers</th>
                </tr>
                </thead>
                <tbody>
                @forelse($distributors as $d)
                    <tr>
                        <td>{{ $d->id }}</td>
                        <td>{{ $d->name }}</td>
                        <td>{{ $d->email }}</td>
                        <td>₹{{ number_format((float) $d->wallets->sum('balance'), 2) }}</td>
                        <td>{{ $retailers->where('distributor_id', $d->id)->count() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No distributors</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Retailers</h3>
            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Distributor</th>
                    <th>Wallet Balance</th>
                </tr>
                </thead>
                <tbody>
                @forelse($retailers as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td>{{ $r->name }}</td>
                        <td>{{ $r->email }}</td>
                        <td>{{ $r->distributor?->name ?? 'Unassigned' }}</td>
                        <td>₹{{ number_format((float) $r->wallets->sum('balance'), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No retailers</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="row">
        <div class="panel">
            <h3>Recent Withdrawals</h3>
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Ref</th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentWithdrawals as $w)
                    <tr>
                        <td>{{ $w->created_at?->format('d-m-Y H:i') }}</td>
                        <td>{{ $w->user?->name ?? '-' }}</td>
                        <td>₹{{ number_format((float)$w->amount, 2) }}</td>
                        <td>
                            @if($w->status === 'completed')
                                <span class="ok">completed</span>
                            @else
                                <span class="warn">{{ $w->status }}</span>
                            @endif
                        </td>
                        <td><span class="pill">{{ $w->reference }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No withdrawals yet</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Recent Commission Credits</h3>
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>User</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Ref</th>
                </tr>
                </thead>
                <tbody>
                @forelse($recentCommissions as $c)
                    <tr>
                        <td>{{ $c->created_at?->format('d-m-Y H:i') }}</td>
                        <td>{{ $c->user?->name ?? '-' }}</td>
                        <td>{{ ucfirst($c->commission_type) }}</td>
                        <td>₹{{ number_format((float)$c->commission_amount, 2) }}</td>
                        <td><span class="pill">{{ $c->reference }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="muted">No commissions yet</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>

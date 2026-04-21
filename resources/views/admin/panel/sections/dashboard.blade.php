@php
    $series = collect($dailyDepositWithdrawSeries ?? [])->values();
    $todayDeposit = (float) ($dailyTrendSummary['today_deposit'] ?? 0);
    $todayWithdrawal = (float) ($dailyTrendSummary['today_withdrawal'] ?? 0);
    $todayNet = (float) ($dailyTrendSummary['today_net'] ?? ($todayDeposit - $todayWithdrawal));
    $yesterday = $series->slice(-2, 1)->first();
    $yesterdayDeposit = (float) data_get($yesterday, 'deposit', 0);
    $yesterdayWithdrawal = (float) data_get($yesterday, 'withdrawal', 0);
    $walletDelta = $stats['total_wallet_balance'] - $stats['admin_main_wallet_balance'];
    $lineMax = max(1, $series->max(fn ($item) => max((float) data_get($item, 'deposit', 0), (float) data_get($item, 'withdrawal', 0))));
    $points = max($series->count() - 1, 1);
    $depositPoints = [];
    $withdrawalPoints = [];
    foreach ($series as $index => $item) {
        $x = $points === 0 ? 0 : ($index / $points) * 100;
        $depositY = 100 - (((float) data_get($item, 'deposit', 0) / $lineMax) * 100);
        $withdrawalY = 100 - (((float) data_get($item, 'withdrawal', 0) / $lineMax) * 100);
        $depositPoints[] = round($x, 2) . ',' . round($depositY, 2);
        $withdrawalPoints[] = round($x, 2) . ',' . round($withdrawalY, 2);
    }
@endphp

<style>
    .admin-dash{display:grid;gap:18px}.hero-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.hero-card,.mini-card,.chart-shell,.report-card{position:relative;overflow:hidden;border-radius:20px;background:rgba(255,255,255,.92);border:1px solid #e3ebf5;box-shadow:0 16px 40px rgba(120,146,182,.14)}.hero-card{padding:18px 18px 20px}.hero-card:before,.mini-card:before{content:"";position:absolute;right:-35px;bottom:-35px;width:160px;height:110px;background:radial-gradient(circle at center,rgba(255,255,255,.75) 0%,rgba(255,255,255,0) 75%)}.hero-top{display:flex;align-items:center;gap:10px;margin-bottom:10px}.hero-icon,.mini-icon{display:grid;place-items:center;color:#fff;font-weight:700}.hero-icon{width:26px;height:26px;border-radius:8px}.hero-label{font-size:.94rem;font-weight:700;color:#41536f}.hero-value{font-size:2rem;font-weight:800;color:#20314e;line-height:1.1}.hero-foot{margin-top:10px;font-size:.92rem;color:#6a7a94;font-weight:600}.trend-up{color:#18b47b}.trend-warn{color:#e9a93d}
    .icon-blue{background:linear-gradient(135deg,#4dafff 0%,#337eff 100%)}.icon-teal{background:linear-gradient(135deg,#5ad9db 0%,#39c6cc 100%)}.icon-amber{background:linear-gradient(135deg,#ffc95b 0%,#f0a72e 100%)}.icon-cobalt{background:linear-gradient(135deg,#548eff 0%,#3560ef 100%)}
    .mini-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.mini-card{padding:14px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}.mini-card-btn{width:100%;border:0;cursor:pointer;text-align:left}.mini-card-btn.active{box-shadow:0 0 0 2px rgba(61,150,255,.18),0 16px 40px rgba(120,146,182,.14)}.mini-left{display:flex;align-items:center;gap:10px}.mini-icon{width:24px;height:24px;border-radius:7px;background:#eaf4ff;color:#3d96ff}.mini-title{font-size:.95rem;font-weight:700;color:#495c79}.mini-value{font-size:1.55rem;font-weight:800;color:#20314e}
    .detail-panel{padding:18px}.detail-panel[hidden]{display:none}.detail-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:14px}.detail-title{font-size:1.08rem;font-weight:800;color:#20314e}.detail-sub{color:#708099;font-size:.88rem}.detail-close{border:1px solid #dce8f7;background:#f7fbff;color:#2a74df;border-radius:12px;padding:8px 14px;cursor:pointer;font-weight:700}
    .detail-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 14px}.detail-search{position:relative;flex:1 1 320px;min-width:240px}.detail-search input{width:100%;height:46px;padding:0 46px 0 44px;border-radius:999px;border:1px solid #dbe7f5;background:linear-gradient(180deg,#ffffff 0%,#f7fbff 100%);color:#233551;outline:0;font-weight:600;box-shadow:inset 0 1px 0 rgba(255,255,255,.65)}.detail-search input:focus{border-color:#99c6ff;box-shadow:0 0 0 4px rgba(61,150,255,.12)}.detail-search-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);color:#7e90ab;font-size:.95rem}.detail-meta{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.detail-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;background:#f6fbff;border:1px solid #dce9f8;color:#5f7290;font-size:.82rem;font-weight:700}.detail-empty{display:none;padding:18px;border:1px dashed #d5e3f3;border-radius:16px;background:#f9fcff;color:#6c7e98;text-align:center;font-weight:700}
    .chart-shell{padding:18px}.chart-head,.report-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}.chart-title,.report-title{font-size:1.3rem;font-weight:800;color:#20314e}.chart-filters{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.filter-pill{padding:8px 14px;border-radius:999px;border:1px solid #e3ebf5;background:#fff;color:#6d7c96;font-weight:700;font-size:.85rem}.filter-pill.active{background:#eef7ff;color:#2273e5;border-color:#cfe3ff}
    .chart-chips{display:flex;gap:10px;flex-wrap:wrap;margin:16px 0 18px}.chart-chip{display:inline-flex;align-items:center;gap:8px;padding:7px 12px;border-radius:999px;background:#f8fbff;border:1px solid #e9f0f8;color:#61718a;font-size:.82rem;font-weight:700}.dot{width:8px;height:8px;border-radius:50%}.dot.dep{background:#39cfff}.dot.wit{background:#7ea0ff}.chart-box{padding:18px;border-radius:18px;background:linear-gradient(180deg,#fcfdff 0%,#f6faff 100%);border:1px solid #edf2f8}.chart-svg{width:100%;height:260px;display:block;overflow:visible}.grid-line{stroke:#e7eef8;stroke-dasharray:3 5;stroke-width:1}.line-dep{fill:none;stroke:#32cfff;stroke-width:3.2;stroke-linecap:round;stroke-linejoin:round}.line-wit{fill:none;stroke:#7d9fff;stroke-width:3.2;stroke-linecap:round;stroke-linejoin:round}.point-dep{fill:#32cfff;stroke:#fff;stroke-width:2}.point-wit{fill:#7d9fff;stroke:#fff;stroke-width:2}.chart-labels{display:grid;grid-template-columns:repeat({{ max($series->count(), 1) }},minmax(0,1fr));gap:8px;margin-top:12px}.chart-label{font-size:.78rem;color:#73829a;text-align:center;white-space:nowrap}
    .report-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}.report-card{padding:18px}.report-link{display:inline-flex;align-items:center;padding:8px 14px;border-radius:999px;background:#f2f8ff;border:1px solid #dceaff;color:#2071e5;font-weight:700;text-decoration:none}.table-wrap{overflow:auto;border-radius:18px}
    @media (max-width:1180px){.hero-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.mini-grid,.report-grid{grid-template-columns:1fr}}@media (max-width:760px){.hero-stats,.mini-grid,.chart-labels{grid-template-columns:1fr}.hero-value{font-size:1.65rem}.mini-card{align-items:flex-start;flex-direction:column}.chart-box{overflow-x:auto}.chart-svg{min-width:700px}.chart-labels{min-width:700px}}
</style>

<section class="admin-dash">
    <div class="hero-stats">
        <article class="hero-card">
            <div class="hero-top"><span class="hero-icon icon-blue">&#128179;</span><span class="hero-label">Admin Main Wallet</span></div>
            <div class="hero-value">&#8377;{{ number_format($stats['admin_main_wallet_balance'], 2) }}</div>
            <div class="hero-foot">Core balance for admin wallet</div>
        </article>
        <article class="hero-card">
            <div class="hero-top"><span class="hero-icon icon-teal">&#9638;</span><span class="hero-label">Total Wallet Balance</span></div>
            <div class="hero-value">&#8377;{{ number_format($stats['total_wallet_balance'], 2) }}</div>
            <div class="hero-foot"><span class="trend-up">+ &#8377;{{ number_format($walletDelta, 2) }}</span> across users</div>
        </article>
        <article class="hero-card">
            <div class="hero-top"><span class="hero-icon icon-amber">&#8377;</span><span class="hero-label">Total Commission Paid</span></div>
            <div class="hero-value">&#8377;{{ number_format($stats['total_commission_paid'], 2) }}</div>
            <div class="hero-foot"><span class="trend-warn">&#9650; &#8377;{{ number_format($stats['total_commission_today'], 2) }}</span> today</div>
        </article>
        <article class="hero-card">
            <div class="hero-top"><span class="hero-icon icon-cobalt">&#8645;</span><span class="hero-label">Total Withdrawals</span></div>
            <div class="hero-value">&#8377;{{ number_format($stats['total_withdrawals'], 2) }}</div>
            <div class="hero-foot"><span class="trend-up">&#9650; &#8377;{{ number_format($stats['total_withdraw_today'], 2) }}</span> today</div>
        </article>
    </div>

    <div class="mini-grid">
        <button
            type="button"
            class="mini-card mini-card-btn"
            id="master-distributors-toggle"
            aria-expanded="false"
            aria-controls="master-distributors-panel"
        >
            <div class="mini-left"><span class="mini-icon">&#128101;</span><span class="mini-title">Master Distributors</span></div>
            <div class="mini-value">{{ $stats['total_master_distributors'] }}</div>
        </button>
        <button
            type="button"
            class="mini-card mini-card-btn"
            id="super-distributors-toggle"
            aria-expanded="false"
            aria-controls="super-distributors-panel"
        >
            <div class="mini-left"><span class="mini-icon">&#128100;</span><span class="mini-title">Super Distributors</span></div>
            <div class="mini-value">{{ $stats['total_super_distributors'] }}</div>
        </button>
        <button
            type="button"
            class="mini-card mini-card-btn"
            id="distributors-toggle"
            aria-expanded="false"
            aria-controls="distributors-panel"
        >
            <div class="mini-left"><span class="mini-icon">&#128101;</span><span class="mini-title">Distributors</span></div>
            <div class="mini-value">{{ $stats['total_distributors'] }}</div>
        </button>
        <article class="mini-card"><div class="mini-left"><span class="mini-icon">&#8595;</span><span class="mini-title">Withdraw Today</span></div><div class="mini-value">&#8377;{{ number_format($stats['total_withdraw_today'], 2) }}</div></article>
        <article class="mini-card"><div class="mini-left"><span class="mini-icon">&#9679;</span><span class="mini-title">Commission Today</span></div><div class="mini-value">&#8377;{{ number_format($stats['total_commission_today'], 2) }}</div></article>
        <button
            type="button"
            class="mini-card mini-card-btn"
            id="retailers-toggle"
            aria-expanded="false"
            aria-controls="retailers-panel"
        >
            <div class="mini-left"><span class="mini-icon">&#128717;</span><span class="mini-title">Retailers</span></div>
            <div class="mini-value">{{ $stats['total_retailers'] }}</div>
        </button>
    </div>

    <section class="chart-shell detail-panel" id="master-distributors-panel" hidden>
        <div class="detail-head">
            <div>
                <div class="detail-title">Master Distributor Details</div>
                <div class="detail-sub">Simple details so you can quickly identify the correct master distributor.</div>
            </div>
            <button type="button" class="detail-close" id="master-distributors-close">Close</button>
        </div>
        <div class="detail-toolbar">
            <label class="detail-search" aria-label="Search master distributors">
                <span class="detail-search-icon">&#128269;</span>
                <input type="text" id="master-distributors-search" placeholder="Search by name, email, phone, status or balance">
            </label>
            <div class="detail-meta">
                <span class="detail-chip">Total: {{ $masterDistributors->count() }}</span>
                <span class="detail-chip" id="master-distributors-count">Showing: {{ $masterDistributors->count() }}</span>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Super Distributors</th>
                        <th>Wallet Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($masterDistributors as $md)
                        <tr data-search-row="master-distributors" data-search-text="{{ strtolower(trim(($md->name ?: '') . ' ' . ($md->email ?: '') . ' ' . ($md->phone ?: '') . ' ' . ($md->is_active ? 'active' : 'inactive') . ' ' . number_format((float) $md->wallets->sum('balance'), 2) . ' ' . $superDistributors->where('distributor_id', $md->id)->count())) }}">
                            <td>{{ $md->name ?: '-' }}</td>
                            <td>{{ $md->email ?: '-' }}</td>
                            <td>{{ $md->phone ?: '-' }}</td>
                            <td>{{ $superDistributors->where('distributor_id', $md->id)->count() }}</td>
                            <td>&#8377;{{ number_format((float) $md->wallets->sum('balance'), 2) }}</td>
                            <td>{{ $md->is_active ? 'Active' : 'Inactive' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No master distributors found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="detail-empty" id="master-distributors-empty">No matching master distributors found.</div>
    </section>

    <section class="chart-shell detail-panel" id="super-distributors-panel" hidden>
        <div class="detail-head">
            <div>
                <div class="detail-title">Super Distributor Details</div>
                <div class="detail-sub">Simple details so you can quickly identify the correct super distributor.</div>
            </div>
            <button type="button" class="detail-close" id="super-distributors-close">Close</button>
        </div>
        <div class="detail-toolbar">
            <label class="detail-search" aria-label="Search super distributors">
                <span class="detail-search-icon">&#128269;</span>
                <input type="text" id="super-distributors-search" placeholder="Search by name, email, phone, parent, status or balance">
            </label>
            <div class="detail-meta">
                <span class="detail-chip">Total: {{ $superDistributors->count() }}</span>
                <span class="detail-chip" id="super-distributors-count">Showing: {{ $superDistributors->count() }}</span>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Master Distributor</th>
                        <th>Distributors</th>
                        <th>Wallet Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($superDistributors as $sd)
                        <tr data-search-row="super-distributors" data-search-text="{{ strtolower(trim(($sd->name ?: '') . ' ' . ($sd->email ?: '') . ' ' . ($sd->phone ?: '') . ' ' . (optional($masterDistributors->firstWhere('id', $sd->distributor_id))->name ?: '') . ' ' . ($sd->is_active ? 'active' : 'inactive') . ' ' . number_format((float) $sd->wallets->sum('balance'), 2) . ' ' . $distributors->where('distributor_id', $sd->id)->count())) }}">
                            <td>{{ $sd->name ?: '-' }}</td>
                            <td>{{ $sd->email ?: '-' }}</td>
                            <td>{{ $sd->phone ?: '-' }}</td>
                            <td>{{ optional($masterDistributors->firstWhere('id', $sd->distributor_id))->name ?: '-' }}</td>
                            <td>{{ $distributors->where('distributor_id', $sd->id)->count() }}</td>
                            <td>&#8377;{{ number_format((float) $sd->wallets->sum('balance'), 2) }}</td>
                            <td>{{ $sd->is_active ? 'Active' : 'Inactive' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No super distributors found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="detail-empty" id="super-distributors-empty">No matching super distributors found.</div>
    </section>

    <section class="chart-shell detail-panel" id="distributors-panel" hidden>
        <div class="detail-head">
            <div>
                <div class="detail-title">Distributor Details</div>
                <div class="detail-sub">Simple details so you can quickly identify the correct distributor.</div>
            </div>
            <button type="button" class="detail-close" id="distributors-close">Close</button>
        </div>
        <div class="detail-toolbar">
            <label class="detail-search" aria-label="Search distributors">
                <span class="detail-search-icon">&#128269;</span>
                <input type="text" id="distributors-search" placeholder="Search by name, email, phone, parent, status or balance">
            </label>
            <div class="detail-meta">
                <span class="detail-chip">Total: {{ $distributors->count() }}</span>
                <span class="detail-chip" id="distributors-count">Showing: {{ $distributors->count() }}</span>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Super Distributor</th>
                        <th>Retailers</th>
                        <th>Wallet Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($distributors as $dist)
                        <tr data-search-row="distributors" data-search-text="{{ strtolower(trim(($dist->name ?: '') . ' ' . ($dist->email ?: '') . ' ' . ($dist->phone ?: '') . ' ' . (optional($superDistributors->firstWhere('id', $dist->distributor_id))->name ?: '') . ' ' . ($dist->is_active ? 'active' : 'inactive') . ' ' . number_format((float) $dist->wallets->sum('balance'), 2) . ' ' . $retailers->where('distributor_id', $dist->id)->count())) }}">
                            <td>{{ $dist->name ?: '-' }}</td>
                            <td>{{ $dist->email ?: '-' }}</td>
                            <td>{{ $dist->phone ?: '-' }}</td>
                            <td>{{ optional($superDistributors->firstWhere('id', $dist->distributor_id))->name ?: '-' }}</td>
                            <td>{{ $retailers->where('distributor_id', $dist->id)->count() }}</td>
                            <td>&#8377;{{ number_format((float) $dist->wallets->sum('balance'), 2) }}</td>
                            <td>{{ $dist->is_active ? 'Active' : 'Inactive' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No distributors found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="detail-empty" id="distributors-empty">No matching distributors found.</div>
    </section>

    <section class="chart-shell detail-panel" id="retailers-panel" hidden>
        <div class="detail-head">
            <div>
                <div class="detail-title">Retailer Details</div>
                <div class="detail-sub">Simple details so you can quickly identify the correct retailer.</div>
            </div>
            <button type="button" class="detail-close" id="retailers-close">Close</button>
        </div>
        <div class="detail-toolbar">
            <label class="detail-search" aria-label="Search retailers">
                <span class="detail-search-icon">&#128269;</span>
                <input type="text" id="retailers-search" placeholder="Search by name, email, phone, distributor, status or balance">
            </label>
            <div class="detail-meta">
                <span class="detail-chip">Total: {{ $retailers->count() }}</span>
                <span class="detail-chip" id="retailers-count">Showing: {{ $retailers->count() }}</span>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Distributor</th>
                        <th>Wallet Balance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($retailers as $retailer)
                        <tr data-search-row="retailers" data-search-text="{{ strtolower(trim(($retailer->name ?: '') . ' ' . ($retailer->email ?: '') . ' ' . ($retailer->phone ?: '') . ' ' . (optional($retailer->distributor)->name ?: '') . ' ' . ($retailer->is_active ? 'active' : 'inactive') . ' ' . number_format((float) $retailer->wallets->sum('balance'), 2))) }}">
                            <td>{{ $retailer->name ?: '-' }}</td>
                            <td>{{ $retailer->email ?: '-' }}</td>
                            <td>{{ $retailer->phone ?: '-' }}</td>
                            <td>{{ optional($retailer->distributor)->name ?: '-' }}</td>
                            <td>&#8377;{{ number_format((float) $retailer->wallets->sum('balance'), 2) }}</td>
                            <td>{{ $retailer->is_active ? 'Active' : 'Inactive' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">No retailers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="detail-empty" id="retailers-empty">No matching retailers found.</div>
    </section>

    <section class="chart-shell">
        <div class="chart-head">
            <div class="chart-title">Deposits vs Withdrawals</div>
            <div class="chart-filters">
                <span class="filter-pill active">10 Days</span>
                <span class="filter-pill">7 Days</span>
                <span class="filter-pill">30 Days</span>
                <span class="filter-pill">3 Month</span>
            </div>
        </div>

        <div class="chart-chips">
            <span class="chart-chip"><span class="dot dep"></span>Today Deposits: &#8377;{{ number_format($todayDeposit, 2) }}</span>
            <span class="chart-chip"><span class="dot wit"></span>Today Withdrawals: &#8377;{{ number_format($todayWithdrawal, 2) }}</span>
            <span class="chart-chip"><span class="dot dep"></span>Net Today: &#8377;{{ number_format($todayNet, 2) }}</span>
            <span class="chart-chip">Yesterday Deposit: &#8377;{{ number_format($yesterdayDeposit, 2) }}</span>
            <span class="chart-chip">Yesterday Withdrawal: &#8377;{{ number_format($yesterdayWithdrawal, 2) }}</span>
        </div>

        <div class="chart-box">
            @if($series->isEmpty())
                <div class="tiny">No data available for daily trend.</div>
            @else
                <svg class="chart-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-label="Daily deposit and withdrawal chart">
                    <line class="grid-line" x1="0" y1="15" x2="100" y2="15"></line>
                    <line class="grid-line" x1="0" y1="35" x2="100" y2="35"></line>
                    <line class="grid-line" x1="0" y1="55" x2="100" y2="55"></line>
                    <line class="grid-line" x1="0" y1="75" x2="100" y2="75"></line>
                    <line class="grid-line" x1="0" y1="95" x2="100" y2="95"></line>
                    <polyline class="line-dep" points="{{ implode(' ', $depositPoints) }}"></polyline>
                    <polyline class="line-wit" points="{{ implode(' ', $withdrawalPoints) }}"></polyline>
                    @foreach($series as $index => $item)
                        @php
                            $x = $points === 0 ? 0 : ($index / $points) * 100;
                            $depY = 100 - (((float) data_get($item, 'deposit', 0) / $lineMax) * 100);
                            $witY = 100 - (((float) data_get($item, 'withdrawal', 0) / $lineMax) * 100);
                        @endphp
                        <circle class="point-dep" cx="{{ round($x, 2) }}" cy="{{ round($depY, 2) }}" r="1.8"></circle>
                        <circle class="point-wit" cx="{{ round($x, 2) }}" cy="{{ round($witY, 2) }}" r="1.8"></circle>
                    @endforeach
                </svg>
                <div class="chart-labels">
                    @foreach($series as $item)
                        <div class="chart-label">{{ data_get($item, 'label', '--') }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="report-grid">
        <article class="report-card">
            <div class="report-head">
                <div class="report-title">Reports</div>
                <a class="report-link" href="{{ route('admin.reports') }}">View All</a>
            </div>
            <div class="chart-chips">
                <span class="chart-chip"><span class="dot dep"></span>Today Deposits: &#8377;{{ number_format($todayDeposit, 2) }}</span>
                <span class="chart-chip"><span class="dot wit"></span>Today Withdrawals: &#8377;{{ number_format($todayWithdrawal, 2) }}</span>
                <span class="chart-chip">Net: &#8377;{{ number_format($todayNet, 2) }}</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Master Distributor</th><th>Email</th><th>Super Distributors</th><th>Balance</th></tr></thead>
                    <tbody>
                        @forelse($masterDistributors as $md)
                            <tr>
                                <td>{{ $md->name }}</td>
                                <td>{{ $md->email }}</td>
                                <td>{{ $superDistributors->where('distributor_id', $md->id)->count() }}</td>
                                <td>&#8377;{{ number_format((float) $md->wallets->sum('balance'), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No master distributors</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="report-card">
            <div class="report-head"><div class="report-title">Latest Withdraw Requests</div></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>User</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($withdrawRequests->take(12) as $wr)
                            <tr>
                                <td>{{ $wr->created_at?->format('d-m H:i') }}</td>
                                <td>{{ $wr->user?->name }}</td>
                                <td>&#8377;{{ number_format((float) $wr->amount, 2) }}</td>
                                <td>{{ ucfirst($wr->status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4">No requests</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</section>

<script>
    (function () {
        const bindings = [
            ['master-distributors-toggle', 'master-distributors-panel', 'master-distributors-close'],
            ['super-distributors-toggle', 'super-distributors-panel', 'super-distributors-close'],
            ['distributors-toggle', 'distributors-panel', 'distributors-close'],
            ['retailers-toggle', 'retailers-panel', 'retailers-close'],
        ];

        const entries = bindings
            .map(([toggleId, panelId, closeId]) => ({
                toggle: document.getElementById(toggleId),
                panel: document.getElementById(panelId),
                closeButton: document.getElementById(closeId),
            }))
            .filter((entry) => entry.toggle && entry.panel && entry.closeButton);

        if (!entries.length) return;

        const searchConfigs = [
            ['master-distributors', 'master-distributors-search', 'master-distributors-count', 'master-distributors-empty'],
            ['super-distributors', 'super-distributors-search', 'super-distributors-count', 'super-distributors-empty'],
            ['distributors', 'distributors-search', 'distributors-count', 'distributors-empty'],
            ['retailers', 'retailers-search', 'retailers-count', 'retailers-empty'],
        ];

        searchConfigs.forEach(([key, inputId, countId, emptyId]) => {
            const input = document.getElementById(inputId);
            const count = document.getElementById(countId);
            const empty = document.getElementById(emptyId);
            const rows = Array.from(document.querySelectorAll(`[data-search-row="${key}"]`));

            if (!input || !count || !empty || !rows.length) return;

            const applyFilter = () => {
                const term = String(input.value || '').trim().toLowerCase();
                let visibleCount = 0;

                rows.forEach((row) => {
                    const lookup = String(row.getAttribute('data-search-text') || '').toLowerCase();
                    const isVisible = !term || lookup.includes(term);
                    row.style.display = isVisible ? '' : 'none';
                    if (isVisible) visibleCount += 1;
                });

                count.textContent = `Showing: ${visibleCount}`;
                empty.style.display = visibleCount === 0 ? 'block' : 'none';
            };

            input.addEventListener('input', applyFilter);
            applyFilter();
        });

        const closePanel = (entry) => {
            entry.panel.hidden = true;
            entry.toggle.classList.remove('active');
            entry.toggle.setAttribute('aria-expanded', 'false');
        };

        const openPanel = (entry) => {
            entries.forEach((item) => {
                if (item !== entry) {
                    closePanel(item);
                }
            });
            entry.panel.hidden = false;
            entry.toggle.classList.add('active');
            entry.toggle.setAttribute('aria-expanded', 'true');
            entry.panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        entries.forEach((entry) => {
            entry.toggle.addEventListener('click', () => {
                if (entry.panel.hidden) {
                    openPanel(entry);
                    return;
                }
                closePanel(entry);
            });

            entry.closeButton.addEventListener('click', () => closePanel(entry));
        });
    })();
</script>

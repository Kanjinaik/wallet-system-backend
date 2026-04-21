            @php
                $pendingWithdrawRequests = $withdrawRequests->where('status', 'pending')->count();
                $approvedWithdrawRequests = $withdrawRequests->where('status', 'approved')->count();
                $rejectedWithdrawRequests = $withdrawRequests->where('status', 'rejected')->count();
                $todayNetFlow = (float) ($dailyTrendSummary['today_net'] ?? 0);
                $currentMonthRevenue = (float) (collect($monthlyRevenue)->last() ?? 0);
                $roleDistribution = [
                    'Master Distributor' => (int) ($stats['total_master_distributors'] ?? 0),
                    'Super Distributor' => (int) ($stats['total_super_distributors'] ?? 0),
                    'Distributor' => (int) ($stats['total_distributors'] ?? 0),
                    'Retailer' => (int) ($stats['total_retailers'] ?? 0),
                ];

                $transactionSeries = collect($dailyDepositWithdrawSeries)->map(function ($row) {
                    return [
                        'date' => (string) ($row['date'] ?? ''),
                        'label' => (string) ($row['label'] ?? ''),
                        'deposit' => (float) ($row['deposit'] ?? 0),
                        'withdrawal' => (float) ($row['withdrawal'] ?? 0),
                        'net' => (float) (($row['deposit'] ?? 0) - ($row['withdrawal'] ?? 0)),
                    ];
                })->values();

                $revenueSeries = collect($monthlyRevenue)->map(function ($value, $month) {
                    return [
                        'month' => (string) $month,
                        'amount' => (float) $value,
                    ];
                })->values();

                $userGrowthSeries = [];
                for ($i = 5; $i >= 0; $i--) {
                    $monthStart = now()->copy()->startOfMonth()->subMonths($i);
                    $monthEnd = $monthStart->copy()->endOfMonth();
                    $newUsers = $allNonAdminUsers->filter(function ($user) use ($monthStart, $monthEnd) {
                        if (!$user->created_at) {
                            return false;
                        }
                        return $user->created_at->between($monthStart, $monthEnd);
                    })->count();

                    $userGrowthSeries[] = [
                        'month' => $monthStart->format('M Y'),
                        'count' => (int) $newUsers,
                    ];
                }

                $detectMethod = function ($transaction) {
                    $haystack = strtolower(
                        trim((string) ($transaction->description ?? '')) . ' ' .
                        trim((string) ($transaction->reference ?? ''))
                    );
                    if (str_contains($haystack, 'upi')) {
                        return 'UPI';
                    }
                    if (str_contains($haystack, 'card') || str_contains($haystack, 'visa') || str_contains($haystack, 'master')) {
                        return 'Card';
                    }
                    if (str_contains($haystack, 'gateway')) {
                        return 'Gateway';
                    }
                    if (str_contains($haystack, 'bank') || str_contains($haystack, 'neft') || str_contains($haystack, 'rtgs') || str_contains($haystack, 'imps')) {
                        return 'Bank';
                    }
                    return 'Other';
                };

                $paymentMethodCounts = ['UPI' => 0, 'Card' => 0, 'Gateway' => 0, 'Bank' => 0, 'Other' => 0];
                foreach ($recentWithdrawals as $txn) {
                    $method = $detectMethod($txn);
                    $paymentMethodCounts[$method] = (int) ($paymentMethodCounts[$method] ?? 0) + 1;
                }

                $topUsers = $recentWithdrawals
                    ->map(function ($txn) {
                        return [
                            'user' => (string) ($txn->user?->name ?? 'Unknown'),
                            'amount' => (float) ($txn->amount ?? 0),
                            'date' => optional($txn->created_at)->format('Y-m-d H:i:s'),
                        ];
                    })
                    ->concat($recentCommissions->map(function ($txn) {
                        return [
                            'user' => (string) ($txn->user?->name ?? 'Unknown'),
                            'amount' => (float) ($txn->commission_amount ?? 0),
                            'date' => optional($txn->created_at)->format('Y-m-d H:i:s'),
                        ];
                    }))
                    ->groupBy('user')
                    ->map(function ($items, $name) {
                        return [
                            'name' => $name,
                            'txn_count' => $items->count(),
                            'total_amount' => (float) $items->sum('amount'),
                            'last_activity' => (string) $items->max('date'),
                        ];
                    })
                    ->sortByDesc('total_amount')
                    ->take(10)
                    ->values();

                $suspiciousTransactions = collect();
                foreach ($recentWithdrawals as $txn) {
                    $status = strtolower((string) ($txn->status ?? ''));
                    $isHigh = (float) ($txn->amount ?? 0) >= 50000;
                    $isBad = in_array($status, ['failed', 'cancelled', 'rejected'], true);
                    if ($isHigh || $isBad) {
                        $suspiciousTransactions->push([
                            'user' => (string) ($txn->user?->name ?? 'Unknown'),
                            'type' => 'withdraw',
                            'amount' => (float) ($txn->amount ?? 0),
                            'reason' => $isHigh ? 'High amount' : 'Failed/Cancelled status',
                            'status' => (string) ($txn->status ?? 'unknown'),
                            'date' => optional($txn->created_at)->format('Y-m-d H:i:s'),
                        ]);
                    }
                }
                foreach ($withdrawRequests as $request) {
                    $status = strtolower((string) ($request->status ?? ''));
                    $isHigh = (float) ($request->amount ?? 0) >= 50000;
                    $isRisky = in_array($status, ['rejected', 'pending'], true);
                    if ($isHigh || $isRisky) {
                        $suspiciousTransactions->push([
                            'user' => (string) ($request->user?->name ?? 'Unknown'),
                            'type' => 'withdraw_request',
                            'amount' => (float) ($request->amount ?? 0),
                            'reason' => $isHigh ? 'High amount request' : 'Pending/Rejected request',
                            'status' => (string) ($request->status ?? 'unknown'),
                            'date' => optional($request->created_at)->format('Y-m-d H:i:s'),
                        ]);
                    }
                }
                $suspiciousTransactions = $suspiciousTransactions
                    ->sortByDesc('date')
                    ->take(20)
                    ->values();
            @endphp

            <section class="cards">
                <article class="card c1"><span>Total Withdraw Today</span><strong>₹{{ number_format($stats['total_withdraw_today'],2) }}</strong></article>
                <article class="card c2"><span>Total Commission Today</span><strong>₹{{ number_format($stats['total_commission_today'],2) }}</strong></article>
                <article class="card c5"><span>Active Users</span><strong>{{ $stats['active_users_count'] }}</strong></article>
                <article class="card c3"><span>Export</span><strong><a href="{{ route('admin.transactions.export') }}" style="color:#fff;text-decoration:underline;">CSV</a> / <a href="#" id="reports-export-excel" style="color:#fff;text-decoration:underline;">Excel</a> / <a href="#" id="reports-print" style="color:#fff;text-decoration:underline;">PDF</a></strong></article>
            </section>

            <section class="cards">
                <article class="card c4"><span>Current Month Revenue</span><strong>₹{{ number_format($currentMonthRevenue,2) }}</strong></article>
                <article class="card c6"><span>Total Commission Paid</span><strong>₹{{ number_format($stats['total_commission_paid'],2) }}</strong></article>
                <article class="card c2"><span>Today's Net Cashflow</span><strong>₹{{ number_format($todayNetFlow,2) }}</strong></article>
                <article class="card c1"><span>Total Withdrawals</span><strong>₹{{ number_format($stats['total_withdrawals'],2) }}</strong></article>
            </section>

            <section class="panel">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                    <h3 style="margin:0;">Date Range Filters</h3>
                    <div class="inline" style="gap:8px;flex-wrap:wrap;">
                        <select id="report-range" style="min-width:140px;">
                            <option value="7">Last 7 Days</option>
                            <option value="15">Last 15 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="all">All</option>
                        </select>
                        <input id="report-from" type="date">
                        <input id="report-to" type="date">
                        <input id="report-search" type="text" placeholder="Search in report tables..." style="min-width:240px;">
                        <button class="btn gray" type="button" id="report-clear">Reset</button>
                    </div>
                </div>
                <p class="tiny" style="margin:0;">Filters update charts and report tables inside this report panel only.</p>
            </section>

            <section class="row2">
                <section class="panel" style="margin-bottom:0;">
                    <h3>Deposit vs Withdrawal Chart</h3>
                    <div style="height:300px;"><canvas id="depositWithdrawChart"></canvas></div>
                </section>
                <section class="panel" style="margin-bottom:0;">
                    <h3>Transaction Trend Graph</h3>
                    <div style="height:300px;"><canvas id="transactionTrendChart"></canvas></div>
                </section>
            </section>

            <section class="row3" style="margin-top:24px;">
                <section class="panel" style="margin-bottom:0;">
                    <h3>Monthly Revenue Chart</h3>
                    <div style="height:260px;"><canvas id="monthlyRevenueChart"></canvas></div>
                </section>
                <section class="panel" style="margin-bottom:0;">
                    <h3>User Growth Chart</h3>
                    <div style="height:260px;"><canvas id="userGrowthChart"></canvas></div>
                </section>
                <section class="panel" style="margin-bottom:0;">
                    <h3>Payment Method Pie Chart</h3>
                    <div style="height:260px;"><canvas id="paymentMethodChart"></canvas></div>
                </section>
            </section>

            <section class="row2" style="margin-top:24px;">
                <section class="panel" style="margin-bottom:0;">
                    <h3>Monthly Revenue (Commission)</h3>
                    <table id="monthly-revenue-table" data-report-filter="true"><thead><tr><th>Month</th><th>Revenue</th></tr></thead><tbody>
                        @foreach($monthlyRevenue as $month => $value)
                            <tr>
                                <td>{{ $month }}</td>
                                <td>₹{{ number_format($value,2) }}</td>
                            </tr>
                        @endforeach
                    </tbody></table>
                </section>

                <section class="panel" style="margin-bottom:0;">
                    <h3>Commission Report</h3>
                    <table data-report-filter="true"><thead><tr><th>User</th><th>Type</th><th>Commission</th><th>Ref</th><th>Date</th></tr></thead><tbody>
                        @forelse($recentCommissions->take(15) as $txn)
                            <tr data-report-date="{{ optional($txn->created_at)->toDateString() }}">
                                <td>{{ $txn->user?->name ?? '-' }}</td>
                                <td>{{ $txn->commission_type ?: '-' }}</td>
                                <td>₹{{ number_format((float) $txn->commission_amount,2) }}</td>
                                <td>{{ $txn->reference ?: '-' }}</td>
                                <td>{{ optional($txn->created_at)->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="tiny">No commission transactions found.</td></tr>
                        @endforelse
                    </tbody></table>
                </section>
            </section>

            <section class="row2" style="margin-top:24px;">
                <section class="panel" style="margin-bottom:0;">
                    <h3>Top Users Table</h3>
                    <table data-report-filter="true"><thead><tr><th>User</th><th>Transactions</th><th>Total Amount</th><th>Last Activity</th></tr></thead><tbody>
                        @forelse($topUsers as $user)
                            <tr data-report-date="{{ substr((string) $user['last_activity'], 0, 10) }}">
                                <td>{{ $user['name'] }}</td>
                                <td>{{ $user['txn_count'] }}</td>
                                <td>₹{{ number_format((float) $user['total_amount'],2) }}</td>
                                <td>{{ $user['last_activity'] ? \Illuminate\Support\Carbon::parse($user['last_activity'])->format('d M Y H:i') : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="tiny">No user activity found.</td></tr>
                        @endforelse
                    </tbody></table>
                </section>

                <section class="panel" style="margin-bottom:0;">
                    <h3>Suspicious Transaction Report</h3>
                    <table data-report-filter="true"><thead><tr><th>User</th><th>Type</th><th>Amount</th><th>Reason</th><th>Status</th><th>Date</th></tr></thead><tbody>
                        @forelse($suspiciousTransactions as $item)
                            <tr data-report-date="{{ substr((string) $item['date'], 0, 10) }}">
                                <td>{{ $item['user'] }}</td>
                                <td>{{ ucfirst((string) $item['type']) }}</td>
                                <td>₹{{ number_format((float) $item['amount'],2) }}</td>
                                <td>{{ $item['reason'] }}</td>
                                <td>{{ ucfirst((string) $item['status']) }}</td>
                                <td>{{ $item['date'] ? \Illuminate\Support\Carbon::parse($item['date'])->format('d M Y H:i') : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="tiny">No suspicious transactions detected for current dataset.</td></tr>
                        @endforelse
                    </tbody></table>
                </section>
            </section>

            <section class="row2" style="margin-top:24px;">
                <section class="panel" style="margin-bottom:0;">
                    <h3>Daily Deposit vs Withdrawal</h3>
                    <table data-report-filter="true"><thead><tr><th>Date</th><th>Deposit</th><th>Withdrawal</th><th>Net</th></tr></thead><tbody>
                        @foreach($dailyDepositWithdrawSeries as $row)
                            @php $net = (float) $row['deposit'] - (float) $row['withdrawal']; @endphp
                            <tr data-report-date="{{ $row['date'] }}">
                                <td>{{ $row['label'] }}</td>
                                <td>₹{{ number_format((float) $row['deposit'],2) }}</td>
                                <td>₹{{ number_format((float) $row['withdrawal'],2) }}</td>
                                <td style="color:{{ $net >= 0 ? '#34d399' : '#f87171' }};">₹{{ number_format($net,2) }}</td>
                            </tr>
                        @endforeach
                    </tbody></table>
                    <p class="tiny">Today: Deposit ₹{{ number_format((float) $dailyTrendSummary['today_deposit'],2) }}, Withdrawal ₹{{ number_format((float) $dailyTrendSummary['today_withdrawal'],2) }}.</p>
                </section>

                <section class="panel" style="margin-bottom:0;">
                    <h3>Withdraw Request Status</h3>
                    <section class="cards" style="grid-template-columns: repeat(3, minmax(0, 1fr)); margin-bottom:14px;">
                        <article class="card c3" style="padding:16px;"><span>Pending</span><strong>{{ $pendingWithdrawRequests }}</strong></article>
                        <article class="card c5" style="padding:16px;"><span>Approved</span><strong>{{ $approvedWithdrawRequests }}</strong></article>
                        <article class="card c1" style="padding:16px;"><span>Rejected</span><strong>{{ $rejectedWithdrawRequests }}</strong></article>
                    </section>
                    <table data-report-filter="true"><thead><tr><th>User</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>
                        @forelse($withdrawRequests->take(12) as $request)
                            <tr data-report-date="{{ optional($request->created_at)->toDateString() }}">
                                <td>{{ $request->user?->name ?? '-' }}</td>
                                <td>₹{{ number_format((float) $request->amount,2) }}</td>
                                <td>{{ ucfirst((string) $request->status) }}</td>
                                <td>{{ optional($request->created_at)->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="tiny">No withdraw requests found.</td></tr>
                        @endforelse
                    </tbody></table>
                </section>
            </section>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                (function () {
                    const rawTransactionSeries = @json($transactionSeries);
                    const revenueSeries = @json($revenueSeries);
                    const userGrowthSeries = @json($userGrowthSeries);
                    const paymentMethodCounts = @json($paymentMethodCounts);
                    const roleDistribution = @json($roleDistribution);

                    const printBtn = document.getElementById('reports-print');
                    if (printBtn) {
                        printBtn.addEventListener('click', function (event) {
                            event.preventDefault();
                            window.print();
                        });
                    }

                    const excelBtn = document.getElementById('reports-export-excel');
                    if (excelBtn) {
                        excelBtn.addEventListener('click', function (event) {
                            event.preventDefault();
                            const table = document.getElementById('monthly-revenue-table');
                            if (!table) return;
                            const html = table.outerHTML.replace(/ /g, '%20');
                            const a = document.createElement('a');
                            a.href = 'data:application/vnd.ms-excel,' + html;
                            a.download = 'monthly_revenue_report.xls';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                        });
                    }

                    const searchInput = document.getElementById('report-search');
                    const clearBtn = document.getElementById('report-clear');
                    const rangeInput = document.getElementById('report-range');
                    const fromInput = document.getElementById('report-from');
                    const toInput = document.getElementById('report-to');
                    const tables = Array.from(document.querySelectorAll('table[data-report-filter="true"]'));

                    function getDateBounds() {
                        const now = new Date();
                        const selected = rangeInput ? rangeInput.value : '30';
                        let from = null;
                        let to = null;

                        if (selected !== 'all') {
                            const days = parseInt(selected, 10);
                            if (!Number.isNaN(days)) {
                                from = new Date(now);
                                from.setDate(now.getDate() - days + 1);
                                to = now;
                            }
                        }

                        if (fromInput && fromInput.value) {
                            from = new Date(fromInput.value + 'T00:00:00');
                        }
                        if (toInput && toInput.value) {
                            to = new Date(toInput.value + 'T23:59:59');
                        }

                        return { from, to };
                    }

                    function rowInDateRange(rowDateStr, bounds) {
                        if (!rowDateStr) return true;
                        const rowDate = new Date(rowDateStr + 'T12:00:00');
                        if (Number.isNaN(rowDate.getTime())) return true;
                        if (bounds.from && rowDate < bounds.from) return false;
                        if (bounds.to && rowDate > bounds.to) return false;
                        return true;
                    }

                    function applyFilter() {
                        const value = ((searchInput && searchInput.value) || '').toLowerCase().trim();
                        const bounds = getDateBounds();
                        tables.forEach(function (table) {
                            const rows = Array.from(table.querySelectorAll('tbody tr'));
                            rows.forEach(function (row) {
                                const text = row.innerText.toLowerCase();
                                const rowDate = row.getAttribute('data-report-date');
                                const visibleByText = value === '' || text.includes(value);
                                const visibleByDate = rowInDateRange(rowDate, bounds);
                                row.style.display = visibleByText && visibleByDate ? '' : 'none';
                            });
                        });
                    }

                    function filterTransactionSeries() {
                        const bounds = getDateBounds();
                        return rawTransactionSeries.filter(function (row) {
                            return rowInDateRange(row.date, bounds);
                        });
                    }

                    if (window.Chart) {
                        const axisColor = 'rgba(148,163,184,0.8)';
                        const gridColor = 'rgba(148,163,184,0.15)';

                        const charts = {};

                        function buildCharts() {
                            const txSeries = filterTransactionSeries();
                            const labels = txSeries.map(function (row) { return row.label; });
                            const deposits = txSeries.map(function (row) { return row.deposit; });
                            const withdrawals = txSeries.map(function (row) { return row.withdrawal; });
                            const netSeries = txSeries.map(function (row) { return row.net; });

                            const baseOptions = {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { labels: { color: '#e2e8f0' } } },
                                scales: {
                                    x: { ticks: { color: axisColor }, grid: { color: gridColor } },
                                    y: { ticks: { color: axisColor }, grid: { color: gridColor } }
                                }
                            };

                            if (charts.depositWithdrawChart) charts.depositWithdrawChart.destroy();
                            if (charts.transactionTrendChart) charts.transactionTrendChart.destroy();
                            if (charts.monthlyRevenueChart) charts.monthlyRevenueChart.destroy();
                            if (charts.userGrowthChart) charts.userGrowthChart.destroy();
                            if (charts.paymentMethodChart) charts.paymentMethodChart.destroy();

                            charts.depositWithdrawChart = new Chart(document.getElementById('depositWithdrawChart'), {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [
                                        { label: 'Deposit', data: deposits, backgroundColor: 'rgba(16,185,129,0.7)', borderColor: '#10b981', borderWidth: 1 },
                                        { label: 'Withdrawal', data: withdrawals, backgroundColor: 'rgba(239,68,68,0.7)', borderColor: '#ef4444', borderWidth: 1 }
                                    ]
                                },
                                options: baseOptions
                            });

                            charts.transactionTrendChart = new Chart(document.getElementById('transactionTrendChart'), {
                                type: 'line',
                                data: {
                                    labels: labels,
                                    datasets: [
                                        { label: 'Net Flow', data: netSeries, borderColor: '#38bdf8', backgroundColor: 'rgba(56,189,248,0.2)', fill: true, tension: 0.35 }
                                    ]
                                },
                                options: baseOptions
                            });

                            charts.monthlyRevenueChart = new Chart(document.getElementById('monthlyRevenueChart'), {
                                type: 'bar',
                                data: {
                                    labels: revenueSeries.map(function (row) { return row.month; }),
                                    datasets: [
                                        { label: 'Revenue', data: revenueSeries.map(function (row) { return row.amount; }), backgroundColor: 'rgba(14,165,233,0.75)', borderColor: '#0ea5e9', borderWidth: 1 }
                                    ]
                                },
                                options: baseOptions
                            });

                            charts.userGrowthChart = new Chart(document.getElementById('userGrowthChart'), {
                                type: 'line',
                                data: {
                                    labels: userGrowthSeries.map(function (row) { return row.month; }),
                                    datasets: [
                                        { label: 'New Users', data: userGrowthSeries.map(function (row) { return row.count; }), borderColor: '#a78bfa', backgroundColor: 'rgba(167,139,250,0.2)', fill: true, tension: 0.3 }
                                    ]
                                },
                                options: baseOptions
                            });

                            const paymentLabels = Object.keys(paymentMethodCounts);
                            const paymentValues = paymentLabels.map(function (label) { return paymentMethodCounts[label]; });

                            charts.paymentMethodChart = new Chart(document.getElementById('paymentMethodChart'), {
                                type: 'doughnut',
                                data: {
                                    labels: paymentLabels,
                                    datasets: [
                                        { data: paymentValues, backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6', '#64748b'] }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: { legend: { labels: { color: '#e2e8f0' } } }
                                }
                            });
                        }

                        buildCharts();

                        [rangeInput, fromInput, toInput].forEach(function (input) {
                            if (input) {
                                input.addEventListener('change', function () {
                                    applyFilter();
                                    buildCharts();
                                });
                            }
                        });
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', applyFilter);
                    }

                    if (clearBtn) {
                        clearBtn.addEventListener('click', function () {
                            if (searchInput) searchInput.value = '';
                            if (fromInput) fromInput.value = '';
                            if (toInput) toInput.value = '';
                            if (rangeInput) rangeInput.value = '30';
                            applyFilter();
                        });
                    }

                    applyFilter();
                })();
            </script>

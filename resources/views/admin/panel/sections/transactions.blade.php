            <section class="panel">
                <form class="inline" method="get" action="{{ route('admin.transactions') }}">
                    <input name="q" value="{{ $search }}" placeholder="Search by user/reference/description">
                    <select name="type"><option value="">All statuses</option><option value="pending" {{ $typeFilter==='pending'?'selected':'' }}>Pending</option><option value="completed" {{ $typeFilter==='completed'?'selected':'' }}>Completed</option><option value="failed" {{ $typeFilter==='failed'?'selected':'' }}>Failed</option><option value="cancelled" {{ $typeFilter==='cancelled'?'selected':'' }}>Cancelled</option></select>
                    <button class="btn" type="submit">Apply</button>
                    <a class="btn green" href="{{ route('admin.transactions.export') }}" style="text-decoration:none;display:inline-block;">Export CSV</a>
                </form>
            </section>
            <section class="row2">
                <article class="panel"><h3>Withdraw Transactions</h3><table><thead><tr><th>Date</th><th>User</th><th>Amount</th><th>Status</th><th>Ref</th></tr></thead><tbody>
                    @forelse($recentWithdrawals as $w)<tr><td>{{ $w->created_at?->format('d-m H:i') }}</td><td>{{ $w->user?->email }}</td><td>&#8377;{{ number_format((float)$w->amount,2) }}</td><td>{{ ucfirst($w->status) }}</td><td>{{ $w->reference }}</td></tr>@empty<tr><td colspan="5">No transactions</td></tr>@endforelse
                </tbody></table></article>
                <article class="panel"><h3>Deposit Transactions</h3><table><thead><tr><th>Date</th><th>User</th><th>Amount</th><th>Status</th><th>Ref</th></tr></thead><tbody>
                    @forelse($recentDeposits as $d)<tr><td>{{ $d->created_at?->format('d-m H:i') }}</td><td>{{ $d->user?->email }}</td><td>&#8377;{{ number_format((float)$d->amount,2) }}</td><td>{{ ucfirst($d->status) }}</td><td>{{ $d->reference }}</td></tr>@empty<tr><td colspan="5">No deposits</td></tr>@endforelse
                </tbody></table></article>
            </section>
            <section class="panel">
                <h3>Recharge Transactions</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Service</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Ref</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentRecharges as $r)
                            @php
                                $isManualRecharge = ($r->metadata['provider'] ?? '') === 'payu_manual';
                                $service = $r->metadata['request']['service'] ?? $r->metadata['service'] ?? 'recharge';
                            @endphp
                            <tr>
                                <td>{{ $r->created_at?->format('d-m H:i') }}</td>
                                <td>{{ $r->user?->email }}</td>
                                <td>{{ ucwords(str_replace('-', ' ', (string) $service)) }}</td>
                                <td>&#8377;{{ number_format((float)$r->amount,2) }}</td>
                                <td>{{ ucfirst($r->status) }}{{ $isManualRecharge ? ' / Manual' : '' }}</td>
                                <td>{{ $r->reference }}</td>
                                <td>
                                    @if($r->status === 'pending' && $isManualRecharge)
                                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                            <form method="post" action="{{ route('admin.transactions.recharge.approve', $r->id) }}">
                                                @csrf
                                                <button class="btn green" type="submit">Approve</button>
                                            </form>
                                            <form method="post" action="{{ route('admin.transactions.recharge.reject', $r->id) }}">
                                                @csrf
                                                <button class="btn danger" type="submit">Reject</button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="tiny">{{ $isManualRecharge ? 'Handled' : '--' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7">No recharge transactions</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

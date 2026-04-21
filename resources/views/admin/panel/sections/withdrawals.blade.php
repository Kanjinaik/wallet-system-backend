            <style>
                .withdraw-requests-table td:nth-child(3),
                .withdraw-requests-table td:nth-child(4),
                .withdraw-requests-table td:nth-child(5){
                    font-weight:700;
                    color:var(--heading);
                }
            </style>
            @include('admin.panel.sections.withdraw-charges')
            <section class="panel">
                <h3>Withdraw Settings</h3>
                <form class="form-grid" method="post" action="{{ route('admin.withdrawals.settings') }}">
                    @csrf
                    <select name="withdraw_approval_mode"><option value="auto" {{ $withdrawConfig['withdraw_approval_mode']==='auto'?'selected':'' }}>Auto Approval</option><option value="manual" {{ $withdrawConfig['withdraw_approval_mode']==='manual'?'selected':'' }}>Manual Approval</option></select>
                    <input type="number" step="0.01" min="0" name="withdraw_min_amount" value="{{ $withdrawConfig['withdraw_min_amount'] }}" required>
                    <input type="number" step="0.01" min="0" name="withdraw_max_per_tx" value="{{ $withdrawConfig['withdraw_max_per_tx'] }}" required>
                    <button class="btn" type="submit">Save Withdraw Settings</button>
                </form>
            </section>
            <section class="panel"><h3>Withdraw Requests</h3>
                <table class="withdraw-requests-table"><thead><tr><th>Date</th><th>User</th><th>Withdraw Amount</th><th>Charges</th><th>Debited Amount</th><th>Remarks</th><th>Actions</th></tr></thead><tbody>
                    @forelse($withdrawRequests as $wr)
                        @php
                            $fee = (float) data_get($wr->metadata, 'withdrawal_fee', max(0, ((float) $wr->amount - (float) ($wr->net_amount ?? $wr->amount))));
                            $debitedAmount = (float) data_get($wr->metadata, 'debited_amount', ((float) $wr->amount + $fee));
                            $remarksText = trim((string) ($wr->remarks ?? ''));
                            if ($remarksText === '') {
                                $remarksText = ucfirst((string) ($wr->status ?? 'pending'));
                            }
                        @endphp
                        <tr>
                            <td>{{ $wr->created_at?->format('d-m H:i') }}</td><td>{{ $wr->user?->email }}</td><td>&#8377;{{ number_format((float)$wr->amount,2) }}</td><td>&#8377;{{ number_format($fee,2) }}</td><td>&#8377;{{ number_format($debitedAmount,2) }}</td><td>{{ $remarksText }}</td>
                            <td>
                                @if(in_array($wr->status,['pending','approved']))
                                    <div class="inline">
                                        <form method="post" action="{{ route('admin.withdrawals.approve',$wr->id) }}">@csrf<input name="remarks" placeholder="Remark"><button class="btn green">Approve</button></form>
                                        <form method="post" action="{{ route('admin.withdrawals.reject',$wr->id) }}">@csrf<input name="remarks" placeholder="Remark"><button class="btn red">Reject</button></form>
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty<tr><td colspan="7">No withdraw requests</td></tr>@endforelse
                </tbody></table>
            </section>

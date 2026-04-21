            <section class="row2">
                <article class="panel">
                    <h3>Default Commission %</h3>
                    @foreach(['retailer','distributor','super_distributor','master_distributor','admin'] as $r)
                        @php $cfg = $commissionConfigs->firstWhere('user_role',$r); @endphp
                        <form class="form-grid" method="post" action="{{ route('admin.commissions.default') }}" style="margin-bottom:8px;">
                            @csrf
                            <input type="hidden" name="user_role" value="{{ $r }}">
                            <input type="hidden" name="distributor_commission" value="{{ $cfg?->distributor_commission ?? 0 }}">
                            <input value="{{ ucfirst($r) }}" disabled>
                            <input type="number" step="0.01" min="0" max="100" name="admin_commission" value="{{ $cfg?->admin_commission ?? 0 }}" required>
                            <button class="btn" type="submit">Save {{ ucfirst($r) }}</button>
                        </form>
                    @endforeach
                </article>
                <article class="panel">
                    <h3>Commission Override (User-specific)</h3>
                    <form class="form-grid" method="post" action="{{ route('admin.commissions.override') }}">
                        @csrf
                        <select name="user_id" required><option value="">User</option>@foreach($allNonAdminUsers as $u)<option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role }})</option>@endforeach</select>
                        <input type="number" step="0.01" min="0" max="100" name="admin_commission" placeholder="Admin %" required>
                        <input type="number" step="0.01" min="0" max="100" name="distributor_commission" placeholder="Distributor %" required>
                        <select name="is_active"><option value="1">Active</option><option value="0">Inactive</option></select>
                        <button class="btn" type="submit">Save Override</button>
                    </form>
                    <table><thead><tr><th>User</th><th>Admin %</th><th>Distributor %</th><th>Status</th><th>Action</th></tr></thead><tbody>
                        @forelse($commissionOverrides as $o)
                            <tr><td>{{ $o->user?->email }}</td><td>{{ $o->admin_commission }}</td><td>{{ $o->distributor_commission }}</td><td>{{ $o->is_active?'Active':'Inactive' }}</td>
                                <td><form method="post" action="{{ route('admin.commissions.override.delete',$o->id) }}">@csrf<button class="btn red">Delete</button></form></td></tr>
                        @empty<tr><td colspan="5">No overrides</td></tr>@endforelse
                    </tbody></table>
                </article>
            </section>
            <section class="panel"><h3>Commission History</h3>
                <table><thead><tr><th>Date</th><th>User</th><th>Type</th><th>Amount</th><th>Ref</th></tr></thead><tbody>
                    @forelse($recentCommissions as $c)<tr><td>{{ $c->created_at?->format('d-m H:i') }}</td><td>{{ $c->user?->email }}</td><td>{{ ucfirst($c->commission_type) }}</td><td>₹{{ number_format((float)$c->commission_amount,2) }}</td><td>{{ $c->reference }}</td></tr>@empty<tr><td colspan="5">No commissions</td></tr>@endforelse
                </tbody></table>
            </section>

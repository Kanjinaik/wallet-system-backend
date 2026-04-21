            <section class="row2">
                <article class="panel"><h3>Admin Action Logs</h3>
                    <table><thead><tr><th>Date</th><th>Admin</th><th>Action</th><th>Target</th><th>IP</th></tr></thead><tbody>
                        @forelse($adminLogs as $l)<tr><td>{{ $l->created_at?->format('d-m H:i') }}</td><td>{{ $l->admin?->email }}</td><td>{{ $l->action }}</td><td>{{ $l->target_type }}#{{ $l->target_id }}</td><td>{{ $l->ip_address }}</td></tr>@empty<tr><td colspan="5">No admin logs</td></tr>@endforelse
                    </tbody></table>
                </article>
                <article class="panel"><h3>Login Activity (Recent)</h3>
                    <table><thead><tr><th>Date</th><th>Email</th><th>Role</th><th>Status</th></tr></thead><tbody>
                        @foreach(\App\Models\User::orderBy('updated_at','desc')->limit(30)->get() as $u)<tr><td>{{ $u->updated_at?->format('d-m H:i') }}</td><td>{{ $u->email }}</td><td>{{ $u->role }}</td><td>{{ $u->is_active?'Active':'Inactive' }}</td></tr>@endforeach
                    </tbody></table>
                </article>
            </section>

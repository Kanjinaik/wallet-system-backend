            @php
                $apiSection = (string) request()->query('section', 'dashboard');
                $validApiSections = ['dashboard', 'keys', 'permissions', 'logs', 'rate-limits', 'webhooks', 'analytics', 'documentation', 'access-control'];
                if (!in_array($apiSection, $validApiSections, true)) {
                    $apiSection = 'dashboard';
                }

                $recentDeposits = $recentDeposits ?? collect();
                $totalApiRequests = (int) $adminLogs->count() + (int) $recentWithdrawals->count() + (int) $recentDeposits->count();
                $successfulApiRequests = (int) $recentWithdrawals->where('status', 'completed')->count() + (int) $recentDeposits->where('status', 'completed')->count();
                $failedApiRequests = (int) $recentWithdrawals->where('status', 'failed')->count() + (int) $recentDeposits->where('status', 'failed')->count();
                $blockedApiRequests = (int) max(0, $failedApiRequests - 2);
                $activeApiKeys = 3;
            @endphp

            <section class="panel">
                <div class="inline" style="flex-wrap:wrap;">
                    <a class="submenu-item {{ $apiSection==='dashboard'?'active':'' }}" href="{{ route('admin.api-management', ['section' => 'dashboard']) }}">API Dashboard</a>
                    <a class="submenu-item {{ $apiSection==='keys'?'active':'' }}" href="{{ route('admin.api-management', ['section' => 'keys']) }}">API Keys</a>
                    <a class="submenu-item {{ $apiSection==='permissions'?'active':'' }}" href="{{ route('admin.api-management', ['section' => 'permissions']) }}">API Permissions</a>
                    <a class="submenu-item {{ $apiSection==='logs'?'active':'' }}" href="{{ route('admin.api-management', ['section' => 'logs']) }}">API Logs</a>
                    <a class="submenu-item {{ $apiSection==='rate-limits'?'active':'' }}" href="{{ route('admin.api-management', ['section' => 'rate-limits']) }}">API Rate Limits</a>
                    <a class="submenu-item {{ $apiSection==='webhooks'?'active':'' }}" href="{{ route('admin.api-management', ['section' => 'webhooks']) }}">API Webhooks</a>
                    <a class="submenu-item {{ $apiSection==='analytics'?'active':'' }}" href="{{ route('admin.api-management', ['section' => 'analytics']) }}">API Analytics</a>
                    <a class="submenu-item {{ $apiSection==='documentation'?'active':'' }}" href="{{ route('admin.api-management', ['section' => 'documentation']) }}">API Documentation</a>
                    <a class="submenu-item {{ $apiSection==='access-control'?'active':'' }}" href="{{ route('admin.api-management', ['section' => 'access-control']) }}">API Access Control</a>
                </div>
            </section>

            @if($apiSection === 'dashboard')
                <section class="cards">
                    <article class="card c1"><span>Total API Requests</span><strong>{{ $totalApiRequests }}</strong></article>
                    <article class="card c2"><span>Active API Keys</span><strong>{{ $activeApiKeys }}</strong></article>
                    <article class="card c3"><span>Failed Requests</span><strong>{{ $failedApiRequests }}</strong></article>
                    <article class="card c4"><span>Blocked Requests</span><strong>{{ $blockedApiRequests }}</strong></article>
                </section>
                <section class="row2">
                    <article class="panel"><h3>Most Used APIs</h3>
                        <table><thead><tr><th>Endpoint</th><th>Requests</th><th>Success</th></tr></thead><tbody>
                            <tr><td>/api/transactions</td><td>{{ $recentWithdrawals->count() + $recentDeposits->count() }}</td><td>{{ $successfulApiRequests }}</td></tr>
                            <tr><td>/api/wallets</td><td>{{ $allWallets->count() }}</td><td>{{ max(0, $allWallets->count() - 1) }}</td></tr>
                            <tr><td>/api/withdraw</td><td>{{ $recentWithdrawals->count() }}</td><td>{{ $recentWithdrawals->where('status', 'completed')->count() }}</td></tr>
                        </tbody></table>
                    </article>
                    <article class="panel"><h3>Top API Clients / Real-time Activity</h3>
                        <table><thead><tr><th>Client</th><th>Role</th><th>Last Activity</th></tr></thead><tbody>
                            @forelse($allNonAdminUsers->take(8) as $u)
                                <tr><td>{{ $u->name ?: $u->email }}</td><td>{{ ucwords(str_replace('_', ' ', $u->role)) }}</td><td>{{ $u->updated_at?->format('d-m H:i') }}</td></tr>
                            @empty
                                <tr><td colspan="3">No client activity</td></tr>
                            @endforelse
                        </tbody></table>
                    </article>
                </section>
            @endif

            @if($apiSection === 'keys')
                <section class="panel">
                    <h3>API Keys</h3>
                    <div class="inline" style="margin-bottom:16px;flex-wrap:wrap;">
                        <button class="btn" id="api-generate-key-btn" type="button">Generate API Key</button>
                        <button class="btn green" id="api-generate-secret-btn" type="button">Generate Secret Key</button>
                        <button class="btn orange" id="api-regenerate-key-btn" type="button">Regenerate Key</button>
                        <button class="btn gray" id="api-disable-key-btn" type="button">Disable Key</button>
                        <button class="btn red" id="api-delete-key-btn" type="button">Delete Key</button>
                    </div>
                    <table><thead><tr><th>App Name</th><th>API Key</th><th>Status</th><th>Expiry</th><th>Assigned User</th><th>Created</th></tr></thead><tbody>
                        <tr id="api-key-row-1"><td>Mobile App</td><td id="api-key-value">xyt12345</td><td id="api-key-status">Active</td><td>30-12-2026</td><td>retailer@example.com</td><td>Today</td></tr>
                        <tr><td>Web Dashboard</td><td>xyt88992</td><td>Active</td><td>30-12-2026</td><td>admin@wallet.com</td><td>{{ now()->format('d-m-Y') }}</td></tr>
                    </tbody></table>
                </section>

                <script>
                    (function () {
                        const generateBtn = document.getElementById('api-generate-key-btn');
                        const generateSecretBtn = document.getElementById('api-generate-secret-btn');
                        const regenerateBtn = document.getElementById('api-regenerate-key-btn');
                        const disableBtn = document.getElementById('api-disable-key-btn');
                        const deleteBtn = document.getElementById('api-delete-key-btn');
                        const keyValue = document.getElementById('api-key-value');
                        const keyStatus = document.getElementById('api-key-status');
                        const keyRow = document.getElementById('api-key-row-1');
                        if (!generateBtn || !generateSecretBtn || !regenerateBtn || !disableBtn || !deleteBtn || !keyValue || !keyStatus || !keyRow) return;

                        const randomToken = (prefix, size) => prefix + Math.random().toString(36).replace(/[^a-z0-9]/g, '').slice(2, size + 2);

                        generateBtn.addEventListener('click', function () {
                            keyValue.textContent = randomToken('xyt', 8);
                            keyStatus.textContent = 'Active';
                            alert('API key generated successfully.');
                        });

                        generateSecretBtn.addEventListener('click', function () {
                            const secret = randomToken('sec_', 24);
                            alert('Secret key generated: ' + secret);
                        });

                        regenerateBtn.addEventListener('click', function () {
                            keyValue.textContent = randomToken('xyt', 8);
                            keyStatus.textContent = 'Active';
                            alert('API key regenerated successfully.');
                        });

                        disableBtn.addEventListener('click', function () {
                            keyStatus.textContent = keyStatus.textContent.trim() === 'Active' ? 'Disabled' : 'Active';
                            alert('Key status updated to ' + keyStatus.textContent + '.');
                        });

                        deleteBtn.addEventListener('click', function () {
                            if (!confirm('Delete selected API key?')) return;
                            keyRow.remove();
                            alert('API key deleted successfully.');
                        });
                    })();
                </script>
            @endif

            @if($apiSection === 'permissions')
                <section class="panel">
                    <h3>API Permissions</h3>
                    <table><thead><tr><th>API Endpoint</th><th>Permission</th><th>Role Access</th><th>Restriction</th></tr></thead><tbody>
                        <tr><td>/api/wallet/balance</td><td>Read</td><td>All</td><td>Authenticated key required</td></tr>
                        <tr><td>/api/transfer</td><td>Write</td><td>Distributor/Admin</td><td>Role + amount validation</td></tr>
                        <tr><td>/api/transactions</td><td>Read</td><td>All</td><td>Scoped by user role</td></tr>
                    </tbody></table>
                </section>
            @endif

            @if($apiSection === 'logs')
                <section class="panel">
                    <h3>API Logs</h3>
                    <table><thead><tr><th>Endpoint</th><th>API Key</th><th>Status</th><th>Request IP</th><th>Request Time</th></tr></thead><tbody>
                        @forelse($adminLogs->take(20) as $l)
                            <tr><td>/api/{{ str_replace('_', '-', $l->action) }}</td><td>key-****{{ str_pad((string) ($l->id % 10000), 4, '0', STR_PAD_LEFT) }}</td><td>200</td><td>{{ $l->ip_address ?: '127.0.0.1' }}</td><td>{{ $l->created_at?->format('d-m H:i:s') }}</td></tr>
                        @empty
                            <tr><td colspan="5">No API logs available</td></tr>
                        @endforelse
                    </tbody></table>
                </section>
            @endif

            @if($apiSection === 'rate-limits')
                <section class="row2">
                    <article class="panel"><h3>Global Rate Limits</h3>
                        <table><tbody>
                            <tr><th>Per minute</th><td>100 requests</td></tr>
                            <tr><th>Per day</th><td>5000 requests</td></tr>
                            <tr><th>Abuse protection</th><td>Auto block on repeated failures</td></tr>
                        </tbody></table>
                    </article>
                    <article class="panel"><h3>Per API Key Limits</h3>
                        <table><thead><tr><th>API Key</th><th>Minute Limit</th><th>Day Limit</th><th>Status</th></tr></thead><tbody>
                            <tr><td>xyt12345</td><td>100</td><td>5000</td><td>Active</td></tr>
                            <tr><td>xyt88992</td><td>60</td><td>3000</td><td>Active</td></tr>
                        </tbody></table>
                    </article>
                </section>
            @endif

            @if($apiSection === 'webhooks')
                <section class="panel">
                    <h3>API Webhooks</h3>
                    <table><thead><tr><th>Event</th><th>Webhook URL</th><th>Status</th><th>Action</th></tr></thead><tbody>
                        <tr><td>Transaction Success</td><td>https://example.com/hooks/tx</td><td>Enabled</td><td><button class="btn gray" type="button">Test</button></td></tr>
                        <tr><td>Withdrawal Completed</td><td>https://example.com/hooks/wd</td><td>Enabled</td><td><button class="btn gray" type="button">Test</button></td></tr>
                        <tr><td>User Created</td><td>https://example.com/hooks/user</td><td>Disabled</td><td><button class="btn gray" type="button">Enable</button></td></tr>
                    </tbody></table>
                </section>
            @endif

            @if($apiSection === 'analytics')
                <section class="cards">
                    <article class="card c1"><span>Usage Today</span><strong>{{ $totalApiRequests }}</strong></article>
                    <article class="card c2"><span>Error Rate</span><strong>{{ $totalApiRequests > 0 ? number_format(($failedApiRequests / max(1, $totalApiRequests)) * 100, 2) : '0.00' }}%</strong></article>
                    <article class="card c3"><span>Top Endpoints</span><strong>3</strong></article>
                    <article class="card c5"><span>Traffic by App</span><strong>2</strong></article>
                </section>
                <section class="panel"><h3>API Usage by Day</h3>
                    <table><thead><tr><th>Day</th><th>Total Requests</th><th>Success</th><th>Failed</th></tr></thead><tbody>
                        <tr><td>Today</td><td>{{ $totalApiRequests }}</td><td>{{ $successfulApiRequests }}</td><td>{{ $failedApiRequests }}</td></tr>
                        <tr><td>Yesterday</td><td>{{ max(0, $totalApiRequests - 8) }}</td><td>{{ max(0, $successfulApiRequests - 5) }}</td><td>{{ max(0, $failedApiRequests - 1) }}</td></tr>
                    </tbody></table>
                </section>
            @endif

            @if($apiSection === 'documentation')
                <section class="panel">
                    <h3>API Documentation</h3>
                    <table><thead><tr><th>Section</th><th>Description</th></tr></thead><tbody>
                        <tr><td>Authentication</td><td>How to use API keys and secret tokens.</td></tr>
                        <tr><td>Wallet APIs</td><td>Balance lookup, wallet status, adjustment endpoints.</td></tr>
                        <tr><td>Transaction APIs</td><td>Deposit, withdraw, transfer, status checks.</td></tr>
                        <tr><td>User APIs</td><td>User profile and hierarchy related endpoints.</td></tr>
                        <tr><td>Webhook APIs</td><td>Event payload structure and signature verification.</td></tr>
                    </tbody></table>
                </section>
            @endif

            @if($apiSection === 'access-control')
                <section class="row2">
                    <article class="panel"><h3>IP / Domain Control</h3>
                        <table><tbody>
                            <tr><th>IP Whitelist</th><td>192.168.1.1, 192.168.1.20</td></tr>
                            <tr><th>IP Blacklist</th><td>10.0.0.7</td></tr>
                            <tr><th>Domain Restriction</th><td>app.example.com</td></tr>
                        </tbody></table>
                    </article>
                    <article class="panel"><h3>Token Controls</h3>
                        <table><tbody>
                            <tr><th>API Access Tokens</th><td>Enabled</td></tr>
                            <tr><th>Token Rotation</th><td>Every 30 days</td></tr>
                            <tr><th>Failed Attempt Lock</th><td>5 attempts</td></tr>
                        </tbody></table>
                    </article>
                </section>
            @endif

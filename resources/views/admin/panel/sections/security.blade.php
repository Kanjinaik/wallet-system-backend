            <section class="panel">
                <h3>Security Controls</h3>
                <form class="form-grid" method="post" action="{{ route('admin.security.settings') }}">
                    @csrf
                    <select name="security_2fa_enforced"><option value="0" {{ $security['security_2fa_enforced']=='0'?'selected':'' }}>2FA Optional</option><option value="1" {{ $security['security_2fa_enforced']=='1'?'selected':'' }}>2FA Enforced</option></select>
                    <input name="security_ip_restriction" value="{{ $security['security_ip_restriction'] }}" placeholder="Allowed IPs comma separated">
                    <input type="number" min="10" max="10000" name="security_rate_limit_per_minute" value="{{ $security['security_rate_limit_per_minute'] }}" placeholder="Rate limit per minute">
                    <input type="number" min="8" max="64" name="security_min_password_length" value="{{ $security['security_min_password_length'] }}" placeholder="Min password length">
                    <button class="btn" type="submit">Save Security Settings</button>
                </form>
            </section>
            <section class="panel"><h3>Current Security Policy</h3>
                <ul>
                    <li>2FA Enforcement: {{ $security['security_2fa_enforced']=='1' ? 'Enabled' : 'Disabled' }}</li>
                    <li>IP Restriction: {{ $security['security_ip_restriction'] ?: 'Not configured' }}</li>
                    <li>Rate Limit Per Minute: {{ $security['security_rate_limit_per_minute'] }}</li>
                    <li>Password Minimum Length: {{ $security['security_min_password_length'] }}</li>
                </ul>
            </section>

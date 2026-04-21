            @php
                $settingsSection = (string) request()->query('section', 'general');
                $validSections = ['general', 'wallet', 'commission', 'withdraw', 'security', 'notification', 'payment-gateway', 'smtp', 'sms', 'maintenance', 'backup', 'currency', 'language', 'logs', 'frontend-server'];
                if (!in_array($settingsSection, $validSections, true)) {
                    $settingsSection = 'general';
                }
            @endphp

            <section class="panel">
                <h3>System Settings</h3>
                <div class="inline" style="flex-wrap:wrap;">
                    <a class="submenu-item {{ $settingsSection==='general'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'general']) }}">General Settings</a>
                    <a class="submenu-item {{ $settingsSection==='wallet'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'wallet']) }}">Wallet Settings</a>
                    <a class="submenu-item {{ $settingsSection==='commission'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'commission']) }}">Commission Settings</a>
                    <a class="submenu-item {{ $settingsSection==='withdraw'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'withdraw']) }}">Withdraw Settings</a>
                    <a class="submenu-item {{ $settingsSection==='security'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'security']) }}">Security Settings</a>
                    <a class="submenu-item {{ $settingsSection==='notification'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'notification']) }}">Notification Settings</a>
                    <a class="submenu-item {{ $settingsSection==='payment-gateway'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'payment-gateway']) }}">Payment Gateway</a>
                    <a class="submenu-item {{ $settingsSection==='smtp'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'smtp']) }}">Email SMTP</a>
                    <a class="submenu-item {{ $settingsSection==='sms'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'sms']) }}">SMS Gateway</a>
                    <a class="submenu-item {{ $settingsSection==='maintenance'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'maintenance']) }}">Maintenance</a>
                    <a class="submenu-item {{ $settingsSection==='backup'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'backup']) }}">Backup & Restore</a>
                    <a class="submenu-item {{ $settingsSection==='currency'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'currency']) }}">Currency</a>
                    <a class="submenu-item {{ $settingsSection==='language'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'language']) }}">Language</a>
                    <a class="submenu-item {{ $settingsSection==='logs'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'logs']) }}">System Logs</a>
                    <a class="submenu-item {{ $settingsSection==='frontend-server'?'active':'' }}" href="{{ route('admin.settings', ['section' => 'frontend-server']) }}">Frontend Server</a>
                </div>
            </section>

            @if($settingsSection === 'general')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="general">
                    <section class="panel">
                        <h3>1. General Settings</h3>
                        <div class="form-grid">
                            <input name="site_name" value="{{ old('site_name', $systemSettings['site_name']) }}" placeholder="Site Name">
                            <input name="support_email" value="{{ old('support_email', $systemSettings['support_email']) }}" placeholder="Support Email">
                            <input name="support_phone" value="{{ old('support_phone', $systemSettings['support_phone']) }}" placeholder="Support Phone">
                            <input name="site_address" value="{{ old('site_address', $systemSettings['site_address']) }}" placeholder="Address">
                            <input name="site_timezone" value="{{ old('site_timezone', $systemSettings['site_timezone']) }}" placeholder="Timezone">
                            <input name="site_currency" value="{{ old('site_currency', $systemSettings['site_currency']) }}" placeholder="Currency">
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save General Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'wallet')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="wallet">
                    <section class="panel">
                        <h3>2. Wallet Settings</h3>
                        <div class="form-grid">
                            <input type="number" step="0.01" min="0" name="min_wallet_balance" value="{{ old('min_wallet_balance', $systemSettings['min_wallet_balance']) }}" placeholder="Minimum Wallet Balance">
                            <input type="number" step="0.01" min="0" name="max_wallet_balance" value="{{ old('max_wallet_balance', $systemSettings['max_wallet_balance']) }}" placeholder="Maximum Wallet Balance">
                            <input type="number" step="0.01" min="0" name="daily_transfer_limit" value="{{ old('daily_transfer_limit', $systemSettings['daily_transfer_limit']) }}" placeholder="Daily Transfer Limit">
                            <input type="number" step="0.01" min="0" name="transaction_fee" value="{{ old('transaction_fee', $systemSettings['transaction_fee']) }}" placeholder="Transaction Fee">
                            <input type="number" step="0.01" min="0" name="wallet_freeze_limit" value="{{ old('wallet_freeze_limit', $systemSettings['wallet_freeze_limit']) }}" placeholder="Wallet Freeze Limit">
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save Wallet Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'commission')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="commission">
                    <section class="panel">
                        <h3>3. Commission Settings</h3>
                        <div class="form-grid">
                            <input type="number" step="0.01" min="0" max="100" name="deposit_commission_master_distributor" value="{{ old('deposit_commission_master_distributor', $systemSettings['deposit_commission_master_distributor']) }}" placeholder="Master Distributor Commission (%)">
                            <input type="number" step="0.01" min="0" max="100" name="deposit_commission_super_distributor" value="{{ old('deposit_commission_super_distributor', $systemSettings['deposit_commission_super_distributor']) }}" placeholder="Super Distributor Commission (%)">
                            <input type="number" step="0.01" min="0" max="100" name="deposit_commission_distributor" value="{{ old('deposit_commission_distributor', $systemSettings['deposit_commission_distributor']) }}" placeholder="Distributor Commission (%)">
                            <input type="number" step="0.01" min="0" max="100" name="deposit_commission_admin" value="{{ old('deposit_commission_admin', $systemSettings['deposit_commission_admin']) }}" placeholder="Admin Commission (%)">
                        </div>
                        <p class="tiny">These values are applied directly in live retailer deposit commission credit flow.</p>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save Commission Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'withdraw')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="withdraw">
                    <section class="panel">
                        <h3>4. Withdraw Settings</h3>
                        <div class="form-grid">
                            <input type="number" step="0.01" min="0" name="withdraw_min_amount" value="{{ old('withdraw_min_amount', $withdrawConfig['withdraw_min_amount']) }}" placeholder="Minimum Withdraw Amount">
                            <input type="number" step="0.01" min="0" name="withdraw_max_per_tx" value="{{ old('withdraw_max_per_tx', $withdrawConfig['withdraw_max_per_tx']) }}" placeholder="Maximum Withdraw Amount">
                            <input type="number" step="0.01" min="0" name="withdraw_charges" value="{{ old('withdraw_charges', $systemSettings['withdraw_charges']) }}" placeholder="Withdraw Charges">
                            <input name="withdraw_processing_time" value="{{ old('withdraw_processing_time', $systemSettings['withdraw_processing_time']) }}" placeholder="Withdraw Processing Time">
                            <label class="tiny" style="display:flex;align-items:center;gap:8px;padding-top:10px;"><input type="hidden" name="withdraw_approval_required" value="0"><input type="checkbox" name="withdraw_approval_required" value="1" {{ old('withdraw_approval_required', $systemSettings['withdraw_approval_required'])=='1'?'checked':'' }}> Withdraw Approval Required</label>
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save Withdraw Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'security')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="security">
                    <section class="panel">
                        <h3>5. Security Settings</h3>
                        <div class="form-grid">
                            <label class="tiny" style="display:flex;align-items:center;gap:8px;padding-top:10px;"><input type="hidden" name="enable_2fa" value="0"><input type="checkbox" name="enable_2fa" value="1" {{ old('enable_2fa', $systemSettings['enable_2fa'])=='1'?'checked':'' }}> Enable 2FA</label>
                            <input type="number" min="1" max="20" name="login_attempt_limit" value="{{ old('login_attempt_limit', $systemSettings['login_attempt_limit']) }}" placeholder="Login Attempt Limit">
                            <input type="number" min="1" max="120" name="otp_expiry_time" value="{{ old('otp_expiry_time', $systemSettings['otp_expiry_time']) }}" placeholder="OTP Expiry Time (minutes)">
                            <label class="tiny" style="display:flex;align-items:center;gap:8px;padding-top:10px;"><input type="hidden" name="ip_blocking" value="0"><input type="checkbox" name="ip_blocking" value="1" {{ old('ip_blocking', $systemSettings['ip_blocking'])=='1'?'checked':'' }}> IP Blocking</label>
                            <input name="password_policy" value="{{ old('password_policy', $systemSettings['password_policy']) }}" placeholder="Password Policy">
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save Security Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'notification')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="notification">
                    <section class="panel">
                        <h3>6. Notification Settings</h3>
                        <div class="form-grid">
                            <label class="tiny" style="display:flex;align-items:center;gap:8px;padding-top:10px;"><input type="hidden" name="email_notifications" value="0"><input type="checkbox" name="email_notifications" value="1" {{ old('email_notifications', $systemSettings['email_notifications'])=='1'?'checked':'' }}> Email Notifications</label>
                            <label class="tiny" style="display:flex;align-items:center;gap:8px;padding-top:10px;"><input type="hidden" name="sms_notifications" value="0"><input type="checkbox" name="sms_notifications" value="1" {{ old('sms_notifications', $systemSettings['sms_notifications'])=='1'?'checked':'' }}> SMS Notifications</label>
                            <label class="tiny" style="display:flex;align-items:center;gap:8px;padding-top:10px;"><input type="hidden" name="admin_alerts" value="0"><input type="checkbox" name="admin_alerts" value="1" {{ old('admin_alerts', $systemSettings['admin_alerts'])=='1'?'checked':'' }}> Admin Alerts</label>
                            <label class="tiny" style="display:flex;align-items:center;gap:8px;padding-top:10px;"><input type="hidden" name="push_notifications" value="0"><input type="checkbox" name="push_notifications" value="1" {{ old('push_notifications', $systemSettings['push_notifications'])=='1'?'checked':'' }}> Push Notifications</label>
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save Notification Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'payment-gateway')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="payment-gateway">
                    <section class="panel">
                        <h3>7. Payment Gateway Settings</h3>
                        <div class="row2">
                            <article>
                                <h4 style="margin:0 0 10px 0;">Ertitech Payout</h4>
                                <div class="form-grid">
                                    <input name="gateway_ertitech_username" value="{{ old('gateway_ertitech_username', $systemSettings['gateway_ertitech_username']) }}" placeholder="Username or Email">
                                    <input name="gateway_ertitech_password" value="{{ old('gateway_ertitech_password', $systemSettings['gateway_ertitech_password']) }}" placeholder="Password">
                                    <input name="gateway_ertitech_merchant_id" value="{{ old('gateway_ertitech_merchant_id', $systemSettings['gateway_ertitech_merchant_id']) }}" placeholder="Merchant ID">
                                    <input name="gateway_ertitech_wallet_id" value="{{ old('gateway_ertitech_wallet_id', $systemSettings['gateway_ertitech_wallet_id']) }}" placeholder="Wallet ID">
                                    <input name="gateway_ertitech_aes_key" value="{{ old('gateway_ertitech_aes_key', $systemSettings['gateway_ertitech_aes_key']) }}" placeholder="AES Hex Key">
                                    <select name="gateway_ertitech_mode"><option value="test" {{ old('gateway_ertitech_mode', $systemSettings['gateway_ertitech_mode'])==='test'?'selected':'' }}>Test</option><option value="live" {{ old('gateway_ertitech_mode', $systemSettings['gateway_ertitech_mode'])==='live'?'selected':'' }}>Live</option></select>
                                </div>
                                <p class="tiny" style="margin-top:10px;">Use the test button to verify the entered Ertitech credentials before saving or switching to live.</p>
                                <div class="inline" style="margin-top:10px;gap:10px;">
                                    <button class="btn" type="submit">Save Gateway Settings</button>
                                    <button class="btn gray" type="submit" formaction="{{ route('admin.settings.ertitech.test') }}">Test Ertitech Connection</button>
                                </div>
                            </article>
                            <article>
                                <h4 style="margin:0 0 10px 0;">PayU Money / Paytm / Stripe</h4>
                                <div class="form-grid">
                                    <input name="gateway_payu_key" value="{{ old('gateway_payu_key', $systemSettings['gateway_payu_key']) }}" placeholder="PayU Merchant Key">
                                    <input name="gateway_payu_salt" value="{{ old('gateway_payu_salt', $systemSettings['gateway_payu_salt']) }}" placeholder="PayU Merchant Salt">
                                    <select name="gateway_payu_mode"><option value="test" {{ old('gateway_payu_mode', $systemSettings['gateway_payu_mode'])==='test'?'selected':'' }}>Test</option><option value="live" {{ old('gateway_payu_mode', $systemSettings['gateway_payu_mode'])==='live'?'selected':'' }}>Live</option></select>
                                    <input name="gateway_paytm_api_key" value="{{ old('gateway_paytm_api_key', $systemSettings['gateway_paytm_api_key']) }}" placeholder="Paytm API Key">
                                    <input name="gateway_paytm_secret_key" value="{{ old('gateway_paytm_secret_key', $systemSettings['gateway_paytm_secret_key']) }}" placeholder="Paytm Secret Key">
                                    <input name="gateway_stripe_api_key" value="{{ old('gateway_stripe_api_key', $systemSettings['gateway_stripe_api_key']) }}" placeholder="Stripe API Key">
                                    <input name="gateway_stripe_secret_key" value="{{ old('gateway_stripe_secret_key', $systemSettings['gateway_stripe_secret_key']) }}" placeholder="Stripe Secret Key">
                                </div>
                            </article>
                        </div>
                        <article style="margin-top:18px;">
                            <h4 style="margin:0 0 10px 0;">Retailer Prepaid Recharge API</h4>
                            <div class="form-grid">
                                <input name="gateway_recharge_provider" value="{{ old('gateway_recharge_provider', $systemSettings['gateway_recharge_provider']) }}" placeholder="Provider Name (use payu)">
                                <input name="gateway_recharge_api_key" value="{{ old('gateway_recharge_api_key', $systemSettings['gateway_recharge_api_key']) }}" placeholder="PayU Recharge Client ID / API Key">
                                <input name="gateway_recharge_secret_key" value="{{ old('gateway_recharge_secret_key', $systemSettings['gateway_recharge_secret_key']) }}" placeholder="PayU Recharge Client Secret">
                                <input name="gateway_recharge_working_key" value="{{ old('gateway_recharge_working_key', $systemSettings['gateway_recharge_working_key']) }}" placeholder="Legacy Working Key (optional)">
                                <input name="gateway_recharge_iv" value="{{ old('gateway_recharge_iv', $systemSettings['gateway_recharge_iv']) }}" placeholder="Legacy IV (optional)">
                                <input name="gateway_recharge_username" value="{{ old('gateway_recharge_username', $systemSettings['gateway_recharge_username']) }}" placeholder="Legacy Username (optional)">
                                <input name="gateway_recharge_password" value="{{ old('gateway_recharge_password', $systemSettings['gateway_recharge_password']) }}" placeholder="Legacy Password (optional)">
                                <input name="gateway_recharge_auth_base_url" value="{{ old('gateway_recharge_auth_base_url', $systemSettings['gateway_recharge_auth_base_url']) }}" placeholder="PayU Auth Base URL">
                                <input name="gateway_recharge_grant_type" value="{{ old('gateway_recharge_grant_type', $systemSettings['gateway_recharge_grant_type']) }}" placeholder="OAuth Grant Type">
                                <input name="gateway_recharge_scope" value="{{ old('gateway_recharge_scope', $systemSettings['gateway_recharge_scope']) }}" placeholder="OAuth Scope">
                                <input name="gateway_recharge_agent_id" value="{{ old('gateway_recharge_agent_id', $systemSettings['gateway_recharge_agent_id']) }}" placeholder="PayU Agent ID">
                                <input name="gateway_recharge_base_url" value="{{ old('gateway_recharge_base_url', $systemSettings['gateway_recharge_base_url']) }}" placeholder="PayU Recharge API Base URL">
                                <select name="gateway_recharge_mode">
                                    <option value="test" {{ old('gateway_recharge_mode', $systemSettings['gateway_recharge_mode'])==='test'?'selected':'' }}>Test</option>
                                    <option value="live" {{ old('gateway_recharge_mode', $systemSettings['gateway_recharge_mode'])==='live'?'selected':'' }}>Live</option>
                                </select>
                            </div>
                            <p class="tiny" style="margin-top:10px;">BBPS is disabled in this project. Only <strong>PayU live prepaid mobile recharge</strong> is supported here. Use PayU recharge OAuth credentials: client id, client secret, agent id, recharge API base URL, and in <strong>live</strong> mode also auth base URL.</p>
                            <p class="tiny" style="margin-top:10px;">Prepaid biller IDs are auto-resolved from PayU using the selected operator, so no BBPS biller mapping is needed.</p>
                        </article>
                    </section>
                </form>
            @endif

            @if($settingsSection === 'smtp')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="smtp">
                    <section class="panel">
                        <h3>8. Email SMTP Settings</h3>
                        <div class="form-grid">
                            <input name="smtp_host" value="{{ old('smtp_host', $systemSettings['smtp_host']) }}" placeholder="SMTP Host">
                            <input type="number" min="1" max="65535" name="smtp_port" value="{{ old('smtp_port', $systemSettings['smtp_port']) }}" placeholder="SMTP Port">
                            <input name="smtp_username" value="{{ old('smtp_username', $systemSettings['smtp_username']) }}" placeholder="SMTP Username">
                            <input name="smtp_password" value="{{ old('smtp_password', $systemSettings['smtp_password']) }}" placeholder="SMTP Password">
                            <select name="smtp_encryption"><option value="none" {{ old('smtp_encryption', $systemSettings['smtp_encryption'])==='none'?'selected':'' }}>None</option><option value="ssl" {{ old('smtp_encryption', $systemSettings['smtp_encryption'])==='ssl'?'selected':'' }}>SSL</option><option value="tls" {{ old('smtp_encryption', $systemSettings['smtp_encryption'])==='tls'?'selected':'' }}>TLS</option></select>
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save SMTP Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'sms')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="sms">
                    <section class="panel">
                        <h3>9. SMS Gateway Settings</h3>
                        <div class="form-grid">
                            <select name="sms_provider"><option value="none" {{ old('sms_provider', $systemSettings['sms_provider'])==='none'?'selected':'' }}>None</option><option value="twilio" {{ old('sms_provider', $systemSettings['sms_provider'])==='twilio'?'selected':'' }}>Twilio</option><option value="msg91" {{ old('sms_provider', $systemSettings['sms_provider'])==='msg91'?'selected':'' }}>MSG91</option><option value="fast2sms" {{ old('sms_provider', $systemSettings['sms_provider'])==='fast2sms'?'selected':'' }}>Fast2SMS</option></select>
                            <input name="sms_api_key" value="{{ old('sms_api_key', $systemSettings['sms_api_key']) }}" placeholder="API Key">
                            <input name="sms_sender_id" value="{{ old('sms_sender_id', $systemSettings['sms_sender_id']) }}" placeholder="Sender ID">
                            <input name="sms_template_id" value="{{ old('sms_template_id', $systemSettings['sms_template_id']) }}" placeholder="Template ID">
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save SMS Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'maintenance')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="maintenance">
                    <section class="panel">
                        <h3>10. Maintenance Mode</h3>
                        <div class="form-grid">
                            <label class="tiny" style="display:flex;align-items:center;gap:8px;padding-top:10px;"><input type="hidden" name="maintenance_mode" value="0"><input type="checkbox" name="maintenance_mode" value="1" {{ old('maintenance_mode', $systemSettings['maintenance_mode'])=='1'?'checked':'' }}> Maintenance ON</label>
                            <input name="maintenance_message" value="{{ old('maintenance_message', $systemSettings['maintenance_message']) }}" placeholder="Maintenance Message">
                            <input type="datetime-local" name="maintenance_start_time" value="{{ old('maintenance_start_time', $systemSettings['maintenance_start_time']) }}" placeholder="Maintenance Start Time">
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save Maintenance Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'backup')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="backup">
                    <section class="panel">
                        <h3>11. Backup & Restore</h3>
                        <div class="form-grid">
                            <select name="backup_schedule"><option value="manual" {{ old('backup_schedule', $systemSettings['backup_schedule'])==='manual'?'selected':'' }}>Manual</option><option value="daily" {{ old('backup_schedule', $systemSettings['backup_schedule'])==='daily'?'selected':'' }}>Daily</option><option value="weekly" {{ old('backup_schedule', $systemSettings['backup_schedule'])==='weekly'?'selected':'' }}>Weekly</option></select>
                            <label class="tiny" style="display:flex;align-items:center;gap:8px;padding-top:10px;"><input type="hidden" name="auto_daily_backup" value="0"><input type="checkbox" name="auto_daily_backup" value="1" {{ old('auto_daily_backup', $systemSettings['auto_daily_backup'])=='1'?'checked':'' }}> Auto Daily Backup</label>
                            <button class="btn gray" type="button">Backup Database</button>
                            <button class="btn gray" type="button">Download Backup</button>
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save Backup Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'currency')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="currency">
                    <section class="panel">
                        <h3>12. Currency Settings</h3>
                        <div class="form-grid">
                            <input name="default_currency_code" value="{{ old('default_currency_code', $systemSettings['default_currency_code']) }}" placeholder="Currency Code (INR/USD)">
                            <input name="default_currency_symbol" value="{{ old('default_currency_symbol', $systemSettings['default_currency_symbol']) }}" placeholder="Currency Symbol (?/$)">
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save Currency Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'language')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="language">
                    <section class="panel">
                        <h3>13. Language Settings</h3>
                        <div class="form-grid">
                            <select name="language_default">
                                <option value="english" {{ old('language_default', $systemSettings['language_default'])==='english'?'selected':'' }}>English</option>
                                <option value="hindi" {{ old('language_default', $systemSettings['language_default'])==='hindi'?'selected':'' }}>Hindi</option>
                                <option value="telugu" {{ old('language_default', $systemSettings['language_default'])==='telugu'?'selected':'' }}>Telugu</option>
                            </select>
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save Language Settings</button></section>
                </form>
            @endif

            @if($settingsSection === 'logs')
                <section class="panel">
                    <h3>14. System Logs</h3>
                    <table><thead><tr><th>Event</th><th>Time</th></tr></thead><tbody>
                        @forelse($adminLogs->take(20) as $log)
                            <tr><td>{{ ucwords(str_replace('_', ' ', $log->action)) }}</td><td>{{ $log->created_at?->format('d-m-Y H:i') }}</td></tr>
                        @empty
                            <tr><td colspan="2">No system logs</td></tr>
                        @endforelse
                    </tbody></table>
                </section>
            @endif

            @if($settingsSection === 'frontend-server')
                <form method="post" action="{{ route('admin.settings.update') }}">
                    @csrf
                    <input type="hidden" name="section" value="frontend-server">
                    <section class="panel">
                        <h3>15. Frontend Server</h3>
                        <div class="form-grid">
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:14px 16px;border:1px solid #d9e4f4;border-radius:14px;background:#f8fbff;">
                                <div>
                                    <div style="font-weight:700;color:#244a7c;">Frontend Access Control</div>
                                    <div class="tiny" style="margin-top:4px;">Turn frontend login access on or off for users.</div>
                                </div>
                                <label style="position:relative;display:inline-flex;align-items:center;cursor:pointer;">
                                    <input type="hidden" name="frontend_enabled" value="0">
                                    <input type="checkbox" name="frontend_enabled" value="1" {{ old('frontend_enabled', $systemSettings['frontend_enabled'])=='1'?'checked':'' }} style="position:absolute;opacity:0;width:0;height:0;">
                                    <span style="display:inline-flex;align-items:center;justify-content:center;min-width:120px;padding:10px 18px;border-radius:999px;background:{{ old('frontend_enabled', $systemSettings['frontend_enabled'])=='1' ? '#198754' : '#dc3545' }};color:#fff;font-weight:700;box-shadow:0 8px 20px rgba(19, 53, 102, 0.12);">
                                        {{ old('frontend_enabled', $systemSettings['frontend_enabled'])=='1' ? 'ON' : 'OFF' }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </section>
                    <section class="panel"><button class="btn" type="submit">Save Frontend Server Setting</button></section>
                </form>
            @endif

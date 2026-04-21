            <section class="panel">
                <h3>Manual Wallet Adjustment</h3>
                <form class="form-grid" method="post" action="{{ route('admin.wallets.adjust') }}">
                    @csrf
                    <select name="wallet_id" required><option value="">Wallet</option>@foreach($allWallets as $w)<option value="{{ $w->id }}">{{ $w->user?->name }} - {{ $w->name }}</option>@endforeach</select>
                    <select name="type" required><option value="add">Add Balance</option><option value="deduct">Deduct Balance</option></select>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required>
                    <input name="remarks" placeholder="Remarks">
                    <button class="btn" type="submit">Apply Adjustment</button>
                </form>
                <style>
                    .wallet-transfer-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:8px}
                    .wallet-transfer-history[hidden]{display:none !important}
                </style>
                <form class="form-grid" method="post" action="{{ route('admin.wallets.transfer') }}" style="margin-top:8px;">
                    @csrf
                    <select name="from_wallet_id" required>
                        <option value="">Select Source Wallet</option>
                        @foreach($allWallets as $w)
                            <option value="{{ $w->id }}">{{ $w->user?->name }} - {{ $w->name }} ({{ number_format((float)$w->balance,2) }})</option>
                        @endforeach
                    </select>
                    <select name="to_wallet_id" required>
                        <option value="">Select Destination Wallet</option>
                        @foreach($allWallets as $w)
                            <option value="{{ $w->id }}">{{ $w->user?->name }} - {{ $w->name }} ({{ number_format((float)$w->balance,2) }})</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required>
                    <input name="description" placeholder="Description (optional)">
                    <div class="wallet-transfer-actions">
                        <button class="btn" type="submit">Transfer</button>
                        <button class="btn gray" type="button" id="wallet-transfer-history-toggle">Transfer History</button>
                    </div>
                </form>
            </section>
            <section class="panel wallet-transfer-history" id="wallet-transfer-history-panel" hidden>
                <h3>Recent Wallet Transfers</h3>
                <table>
                    <thead>
                        <tr><th>Date</th><th>From Wallet</th><th>To Wallet</th><th>Amount</th><th>Ref</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse(\App\Models\Transaction::with(['fromWallet.user','toWallet.user'])->where('type','transfer')->orderBy('created_at','desc')->limit(50)->get() as $tx)
                            <tr>
                                <td>{{ $tx->created_at?->format('d-m H:i') }}</td>
                                <td>{{ $tx->fromWallet?->user?->name }} - {{ $tx->fromWallet?->name }}</td>
                                <td>{{ $tx->toWallet?->user?->name }} - {{ $tx->toWallet?->name }}</td>
                                <td>{{ number_format((float)$tx->amount,2) }}</td>
                                <td>{{ $tx->reference }}</td>
                                <td>{{ ucfirst($tx->status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No transfers found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
            <section class="panel">
                <h3>Wallet Ledger / Freeze Control</h3>
                <table><thead><tr><th>ID</th><th>User</th><th>Wallet</th><th>Type</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead><tbody>
                    @forelse($allWallets as $w)
                        @php
                            $isRetailerWallet = in_array($w->user?->role, ['retailer', 'user'], true);
                        @endphp
                        <tr>
                            <td>{{ $w->id }}</td><td>{{ $w->user?->email }}</td><td>{{ $w->name }}</td><td>{{ $w->type }}</td><td>{{ number_format((float)$w->balance,2) }}</td><td>{{ $w->is_frozen?'Frozen':'Active' }}</td>
                            <td>
                                @if($w->is_frozen)
                                    <form method="post" action="{{ route('admin.wallets.toggle',$w->id) }}">@csrf<button class="btn green">Unfreeze</button></form>
                                @elseif($isRetailerWallet)
                                    <button
                                        type="button"
                                        class="btn orange js-freeze-wallet-btn"
                                        data-action="{{ route('admin.wallets.toggle',$w->id) }}"
                                        data-user="{{ $w->user?->name ?: $w->user?->email ?: 'Retailer' }}"
                                        data-wallet="{{ $w->name }}"
                                        data-balance="{{ number_format((float) $w->balance, 2, '.', '') }}"
                                    >
                                        Freeze
                                    </button>
                                @else
                                    <button type="button" class="btn gray" disabled title="Freeze amount popup is available only for retailer wallets">Freeze</button>
                                @endif
                            </td>
                        </tr>
                    @empty<tr><td colspan="7">No wallets</td></tr>@endforelse
                </tbody></table>

                <div class="password-modal-backdrop" id="freeze-wallet-modal-backdrop">
                    <div class="password-modal">
                        <h4 id="freeze-wallet-modal-title">Freeze Retailer Wallet</h4>
                        <form method="post" id="freeze-wallet-modal-form">
                            @csrf
                            <div class="form-grid-2">
                                <div>
                                    <label for="freeze-wallet-amount" class="w-label">Freeze Amount</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" id="freeze-wallet-amount" placeholder="Enter amount" required>
                                </div>
                                <div>
                                    <label for="freeze-wallet-remarks" class="w-label">Remarks</label>
                                    <input type="text" name="remarks" id="freeze-wallet-remarks" placeholder="Optional remarks">
                                </div>
                            </div>
                            <p class="tiny" id="freeze-wallet-modal-copy" style="margin:14px 0 0 0;">Enter the amount to freeze from the retailer wallet.</p>
                            <div class="actions">
                                <button type="button" class="btn gray" id="freeze-wallet-cancel">Cancel</button>
                                <button type="submit" class="btn orange">Freeze Wallet</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
            <section class="panel"><h3>Wallet Adjustment Logs</h3>
                <table><thead><tr><th>Date</th><th>Admin</th><th>User</th><th>Type</th><th>Amount</th><th>Ref</th><th>Remarks</th></tr></thead><tbody>
                    @forelse($walletAdjustments as $l)<tr><td>{{ $l->created_at?->format('d-m H:i') }}</td><td>{{ $l->admin?->email }}</td><td>{{ $l->user?->email }}</td><td>{{ $l->type }}</td><td>{{ number_format((float)$l->amount,2) }}</td><td>{{ $l->reference }}</td><td>{{ $l->remarks }}</td></tr>@empty<tr><td colspan="7">No logs</td></tr>@endforelse
                </tbody></table>
            </section>
            <script>
                (function () {
                    const backdrop = document.getElementById('freeze-wallet-modal-backdrop');
                    const form = document.getElementById('freeze-wallet-modal-form');
                    const amountInput = document.getElementById('freeze-wallet-amount');
                    const remarksInput = document.getElementById('freeze-wallet-remarks');
                    const modalTitle = document.getElementById('freeze-wallet-modal-title');
                    const modalCopy = document.getElementById('freeze-wallet-modal-copy');
                    const cancelButton = document.getElementById('freeze-wallet-cancel');
                    const triggerButtons = document.querySelectorAll('.js-freeze-wallet-btn');

                    if (!backdrop || !form || !amountInput || !remarksInput || !cancelButton || !triggerButtons.length) {
                        return;
                    }

                    const closeModal = () => {
                        backdrop.style.display = 'none';
                        form.removeAttribute('action');
                        form.reset();
                    };

                    triggerButtons.forEach((button) => {
                        button.addEventListener('click', () => {
                            const userName = button.dataset.user || 'Retailer';
                            const walletName = button.dataset.wallet || 'Wallet';
                            const balance = button.dataset.balance || '0.00';

                            form.action = button.dataset.action;
                            modalTitle.textContent = 'Freeze ' + userName + ' Wallet';
                            modalCopy.textContent = walletName + ' balance: Rs. ' + balance + '. Enter the amount to freeze from this retailer wallet.';
                            remarksInput.value = 'Retailer wallet frozen by admin';
                            backdrop.style.display = 'flex';
                            amountInput.focus();
                        });
                    });

                    cancelButton.addEventListener('click', closeModal);
                    backdrop.addEventListener('click', (event) => {
                        if (event.target === backdrop) {
                            closeModal();
                        }
                    });

                    const transferHistoryToggle = document.getElementById('wallet-transfer-history-toggle');
                    const transferHistoryPanel = document.getElementById('wallet-transfer-history-panel');

                    if (transferHistoryToggle && transferHistoryPanel) {
                        transferHistoryToggle.addEventListener('click', () => {
                            const isHidden = transferHistoryPanel.hasAttribute('hidden');
                            if (isHidden) {
                                transferHistoryPanel.removeAttribute('hidden');
                                transferHistoryToggle.textContent = 'Hide History';
                            } else {
                                transferHistoryPanel.setAttribute('hidden', 'hidden');
                                transferHistoryToggle.textContent = 'Transfer History';
                            }
                        });
                    }
                })();
            </script>

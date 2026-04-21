            <section class="panel">
                <h3>Transfer Wallet To Wallet</h3>
                <form class="form-grid" method="post" action="{{ route('admin.wallets.transfer') }}">
                    @csrf
                    <select name="from_wallet_id" required>
                        <option value="">Select Source Wallet</option>
                        @foreach($allWallets as $w)
                            <option value="{{ $w->id }}">{{ $w->user?->name }} - {{ $w->name }} (₹{{ number_format((float)$w->balance,2) }})</option>
                        @endforeach
                    </select>
                    <select name="to_wallet_id" required>
                        <option value="">Select Destination Wallet</option>
                        @foreach($allWallets as $w)
                            <option value="{{ $w->id }}">{{ $w->user?->name }} - {{ $w->name }} (₹{{ number_format((float)$w->balance,2) }})</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" min="0.01" name="amount" placeholder="Amount" required>
                    <input name="description" placeholder="Description (optional)">
                    <button class="btn" type="submit">Transfer</button>
                </form>
            </section>

            <section class="panel">
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
                                <td>₹{{ number_format((float)$tx->amount,2) }}</td>
                                <td>{{ $tx->reference }}</td>
                                <td>{{ ucfirst($tx->status) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">No transfers found</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

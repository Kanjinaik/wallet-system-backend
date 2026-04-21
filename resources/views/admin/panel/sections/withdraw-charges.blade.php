@php
    $totalCharges = (float) $withdrawChargeTransactions->sum(fn($t) => (float) data_get($t->metadata, 'withdrawal_fee', 0));
    $totalDebited = (float) $withdrawChargeTransactions->sum(function ($t) {
        $fee = (float) data_get($t->metadata, 'withdrawal_fee', 0);
        return (float) data_get($t->metadata, 'debited_amount', ((float) $t->amount + $fee));
    });
    $averageCharge = $withdrawChargeTransactions->count() > 0
        ? $totalCharges / $withdrawChargeTransactions->count()
        : 0;
@endphp

<style>
    .withdraw-charge-shell{display:grid;gap:18px}
    .withdraw-charge-toolbar{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:18px}
    .withdraw-charge-title{display:grid;gap:6px}
    .withdraw-charge-title h3{margin:0}
    .withdraw-charge-title p{margin:0;color:var(--muted);font-size:.9rem}
    .withdraw-charge-note{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:999px;background:#f6fbff;border:1px solid #dce9f8;color:#5f7290;font-size:.82rem;font-weight:700}
    .withdraw-charge-table td:nth-child(3),
    .withdraw-charge-table td:nth-child(4),
    .withdraw-charge-table td:nth-child(5){font-weight:700;color:var(--heading)}
    .withdraw-charge-table td:last-child{font-family:Consolas,"Courier New",monospace;font-size:.84rem;color:#47607f}
    .withdraw-charge-table tbody tr td:first-child{white-space:nowrap}
    .withdraw-charge-table tbody tr td:nth-child(2){word-break:break-word}
    @media (max-width:760px){
        .withdraw-charge-note{width:100%;justify-content:center}
    }
</style>

<div class="withdraw-charge-shell">
<section class="cards">
    <article class="card c1">
        <span>Total Charged</span>
        <strong>&#8377;{{ number_format($totalCharges, 2) }}</strong>
    </article>
    <article class="card c2">
        <span>Charge Transactions</span>
        <strong>{{ $withdrawChargeTransactions->count() }}</strong>
    </article>
    <article class="card c3">
        <span>Total Debited</span>
        <strong>&#8377;{{ number_format($totalDebited, 2) }}</strong>
    </article>
    <article class="card c4">
        <span>Average Charge</span>
        <strong>&#8377;{{ number_format($averageCharge, 2) }}</strong>
    </article>
</section>
</div>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agent Profile</title>
    <style>
        :root { --blue:#1459c8; --blue2:#2c86f7; --bg:#cfe2ff; --panel:#ffffff; --text:#15233f; --muted:#5d6f8d; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif; color:var(--text); background:
            radial-gradient(circle at 12% 14%, rgba(91, 156, 255, .38) 0%, rgba(91, 156, 255, 0) 36%),
            radial-gradient(circle at 88% 78%, rgba(106, 173, 255, .32) 0%, rgba(106, 173, 255, 0) 38%),
            linear-gradient(180deg, #d8e8ff 0%, #c7ddff 45%, #b0d0ff 100%);
            background-attachment: fixed;
        }
        .wrap { max-width:1200px; margin:0 auto; padding:18px; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:12px; flex-wrap:wrap; }
        .title { margin:0; font-size:30px; }
        .pill { background:#fff; border-radius:999px; padding:8px 12px; box-shadow:0 8px 20px rgba(14,35,77,.10); font-size:13px; }
        .btn { border:0; border-radius:8px; padding:9px 12px; cursor:pointer; color:#fff; background:#1b67d5; text-decoration:none; display:inline-block; }
        .btn.gray { background:#697892; }
        .hero { background:#fff; border-radius:14px; box-shadow:0 8px 20px rgba(31,56,98,.08); padding:14px; display:grid; grid-template-columns:auto 1fr auto; gap:12px; align-items:center; }
        .avatar { width:72px; height:72px; border-radius:999px; overflow:hidden; display:grid; place-items:center; background:#f1f5fd; color:#8ea0c5; font-size:26px; border:1px solid #e1e8f6; }
        .avatar img { width:100%; height:100%; object-fit:cover; }
        .name { font-size:22px; font-weight:700; margin:0; }
        .sub { margin-top:3px; color:#6c7f9f; font-size:14px; }
        .badges { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
        .badge { display:inline-flex; align-items:center; padding:5px 10px; border-radius:8px; color:#fff; font-size:12px; font-weight:700; }
        .badge.role { background:#e52a10; }
        .badge.active { background:#2ca84f; }
        .badge.inactive { background:#a4acbb; }
        .grid { margin-top:12px; display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .panel { background:var(--panel); border-radius:12px; box-shadow:0 8px 20px rgba(31,56,98,.08); padding:12px; }
        .panel h3 { margin:2px 0 10px; font-size:20px; }
        .kv { display:grid; grid-template-columns:180px 1fr; gap:8px; padding:6px 0; border-bottom:1px solid #edf2fa; }
        .kv:last-child { border-bottom:0; }
        .k { color:var(--muted); font-size:13px; }
        .v { color:#263c66; font-size:14px; word-break:break-word; }
        .doc-link { color:#1459c8; text-decoration:none; font-weight:600; }
        .doc-link:hover { text-decoration:underline; }
        .review-callout { display:flex; justify-content:space-between; align-items:center; gap:12px; padding:12px; border-radius:10px; border:1px solid #d9e5f7; background:#f8fbff; margin-bottom:12px; }
        .review-actions { display:flex; gap:8px; flex-wrap:wrap; }
        .preview-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:10px; margin-top:12px; }
        .preview-card { border:1px solid #e4ebf8; border-radius:10px; padding:10px; background:#f8fbff; }
        .preview-card strong { display:block; margin-bottom:8px; font-size:13px; color:#26406a; }
        .preview-card img { width:100%; height:180px; object-fit:cover; border-radius:8px; border:1px solid #d9e5f7; background:#fff; }
        .preview-file { display:flex; align-items:center; justify-content:center; min-height:180px; border-radius:8px; border:1px dashed #bfd1ee; background:#fff; padding:12px; text-align:center; }
        @media(max-width:980px){ .grid{grid-template-columns:1fr;} .hero{grid-template-columns:1fr; text-align:left;} .badges{justify-content:flex-start;} }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1 class="title">Agent Profile</h1>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <div class="pill">{{ $admin?->name }} | {{ $admin?->email }}</div>
            <a class="btn gray" href="{{ route('admin.users', ['section' => 'users']) }}">← Back to Users</a>
        </div>
    </div>

    @php
        $photoUrl = $user->profile_photo_path ? asset('storage/' . $user->profile_photo_path) : null;
        $roleLabel = ucwords(str_replace('_', ' ', $user->role));
        $fullName = trim(($user->name ?? '') . ' ' . ($user->last_name ?? ''));
        $override = $user->commissionOverride;
        $parentUser = $user->distributor;
        $primaryWallet = $user->wallets->first();
        $dailyLimit = $user->walletLimits->firstWhere('limit_type', 'daily');
        $monthlyLimit = $user->walletLimits->firstWhere('limit_type', 'monthly');
        $perTxLimit = $user->walletLimits->firstWhere('limit_type', 'per_transaction');
        $docLink = static function (?string $path) {
            return $path ? asset('storage/' . $path) : null;
        };
        $isImage = static function (?string $path) {
            if (!$path) {
                return false;
            }

            return \Illuminate\Support\Str::endsWith(strtolower($path), ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg']);
        };
    @endphp

    <section class="hero">
        <div class="avatar">
            @if($photoUrl)
                <img src="{{ $photoUrl }}" alt="{{ $user->name }}">
            @else
                <span>◌</span>
            @endif
        </div>
        <div>
            <p class="name">{{ $fullName !== '' ? $fullName : '-' }}</p>
            <p class="sub">Agent ID: {{ $user->agent_id }} | Email: {{ $user->email ?: '-' }} | Mobile: {{ $user->phone ?: '-' }}</p>
        </div>
        <div class="badges">
            <span class="badge role">{{ $roleLabel }}</span>
            <span class="badge {{ $user->is_active ? 'active' : 'inactive' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
        </div>
    </section>

    <div class="grid">
        <section class="panel">
            <h3>Personal Details</h3>
            <div class="kv"><div class="k">First Name</div><div class="v">{{ $user->name ?: '-' }}</div></div>
            <div class="kv"><div class="k">Last Name</div><div class="v">{{ $user->last_name ?: '-' }}</div></div>
            <div class="kv"><div class="k">Role</div><div class="v">{{ $roleLabel }}</div></div>
            <div class="kv"><div class="k">Status</div><div class="v">{{ $user->is_active ? 'Active' : 'Inactive' }}</div></div>
            <div class="kv"><div class="k">Email</div><div class="v">{{ $user->email ?: '-' }}</div></div>
            <div class="kv"><div class="k">Mobile</div><div class="v">{{ $user->phone ?: '-' }}</div></div>
            <div class="kv"><div class="k">Alternate Mobile</div><div class="v">{{ $user->alternate_mobile ?: '-' }}</div></div>
            <div class="kv"><div class="k">Business Name</div><div class="v">{{ $user->business_name ?: '-' }}</div></div>
            <div class="kv"><div class="k">Date of Birth</div><div class="v">{{ $user->date_of_birth ? $user->date_of_birth->format('d-m-Y') : '-' }}</div></div>
            <div class="kv"><div class="k">Address</div><div class="v">{{ $user->address ?: '-' }}</div></div>
            <div class="kv"><div class="k">City</div><div class="v">{{ $user->city ?: '-' }}</div></div>
            <div class="kv"><div class="k">State</div><div class="v">{{ $user->state ?: '-' }}</div></div>
            <div class="kv"><div class="k">Parent User</div><div class="v">{{ $parentUser?->name ?: '-' }}</div></div>
            <div class="kv"><div class="k">Parent Agent ID</div><div class="v">{{ $parentUser?->agent_id ?: '-' }}</div></div>
            <div class="kv"><div class="k">Parent Email</div><div class="v">{{ $parentUser?->email ?: '-' }}</div></div>
            <div class="kv"><div class="k">Created On</div><div class="v">{{ $user->created_at ? $user->created_at->format('d-m-Y h:i A') : '-' }}</div></div>
            <div class="kv"><div class="k">Last Updated</div><div class="v">{{ $user->updated_at ? $user->updated_at->format('d-m-Y h:i A') : '-' }}</div></div>
        </section>

        <section class="panel">
            <h3>eKYC Details</h3>
            <div class="review-callout">
                <div>
                    <div style="font-weight:700; color:#26406a;">Admin eKYC Review</div>
                    <div class="sub" style="margin-top:2px;">Use the existing edit page to approve or reject this retailer's eKYC after checking the uploaded files below.</div>
                </div>
                <div class="review-actions">
                    <a class="btn" href="{{ route('admin.users.edit', $user->id) }}">Open Review</a>
                </div>
            </div>
            <div class="kv"><div class="k">KYC ID Number</div><div class="v">{{ $user->kyc_id_number ?: '-' }}</div></div>
            <div class="kv"><div class="k">KYC Status</div><div class="v">{{ $user->kyc_status ?: '-' }}</div></div>
            <div class="kv"><div class="k">Document Type</div><div class="v">{{ $user->kyc_document_type ?: '-' }}</div></div>
            <div class="kv"><div class="k">Liveness Verified</div><div class="v">{{ $user->kyc_liveness_verified ? 'Yes' : 'No' }}</div></div>
            <div class="kv"><div class="k">Profile Photo</div><div class="v">@if($photoUrl)<a class="doc-link" href="{{ $photoUrl }}" target="_blank">View Photo</a>@else-@endif</div></div>
            <div class="kv"><div class="k">KYC Photo</div><div class="v">@if($docLink($user->kyc_photo_path))<a class="doc-link" href="{{ $docLink($user->kyc_photo_path) }}" target="_blank">View Document</a>@else-@endif</div></div>
            <div class="kv"><div class="k">KYC Document</div><div class="v">@if($docLink($user->kyc_document_path))<a class="doc-link" href="{{ $docLink($user->kyc_document_path) }}" target="_blank">View Document</a>@else-@endif</div></div>
            <div class="kv"><div class="k">Address Proof Front</div><div class="v">@if($docLink($user->address_proof_front_path))<a class="doc-link" href="{{ $docLink($user->address_proof_front_path) }}" target="_blank">View Document</a>@else-@endif</div></div>
            <div class="kv"><div class="k">Address Proof Back</div><div class="v">@if($docLink($user->address_proof_back_path))<a class="doc-link" href="{{ $docLink($user->address_proof_back_path) }}" target="_blank">View Document</a>@else-@endif</div></div>
            <div class="kv"><div class="k">KYC Selfie</div><div class="v">@if($docLink($user->kyc_selfie_path))<a class="doc-link" href="{{ $docLink($user->kyc_selfie_path) }}" target="_blank">View Document</a>@else-@endif</div></div>
            <div class="preview-grid">
                @foreach([
                    'Profile Photo' => ['path' => $user->profile_photo_path, 'url' => $photoUrl],
                    'KYC Photo' => ['path' => $user->kyc_photo_path, 'url' => $docLink($user->kyc_photo_path)],
                    'KYC Document' => ['path' => $user->kyc_document_path, 'url' => $docLink($user->kyc_document_path)],
                    'Address Proof Front' => ['path' => $user->address_proof_front_path, 'url' => $docLink($user->address_proof_front_path)],
                    'Address Proof Back' => ['path' => $user->address_proof_back_path, 'url' => $docLink($user->address_proof_back_path)],
                    'KYC Selfie' => ['path' => $user->kyc_selfie_path, 'url' => $docLink($user->kyc_selfie_path)],
                ] as $label => $doc)
                    @if($doc['url'])
                        <div class="preview-card">
                            <strong>{{ $label }}</strong>
                            @if($isImage($doc['path']))
                                <a href="{{ $doc['url'] }}" target="_blank">
                                    <img src="{{ $doc['url'] }}" alt="{{ $label }}">
                                </a>
                            @else
                                <div class="preview-file">
                                    <a class="doc-link" href="{{ $doc['url'] }}" target="_blank">Open {{ $label }}</a>
                                </div>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </section>

        <section class="panel">
            <h3>Bank Account Details</h3>
            <div class="kv"><div class="k">Account Holder Name</div><div class="v">{{ $user->bank_account_name ?: '-' }}</div></div>
            <div class="kv"><div class="k">Account Number</div><div class="v">{{ $user->bank_account_number ?: '-' }}</div></div>
            <div class="kv"><div class="k">IFSC Code</div><div class="v">{{ $user->bank_ifsc_code ?: '-' }}</div></div>
            <div class="kv"><div class="k">Bank Name</div><div class="v">{{ $user->bank_name ?: '-' }}</div></div>
            <div class="kv"><div class="k">Primary Wallet</div><div class="v">{{ $primaryWallet?->name ?: '-' }}</div></div>
            <div class="kv"><div class="k">Wallet Type</div><div class="v">{{ $primaryWallet?->type ? ucwords($primaryWallet->type) : '-' }}</div></div>
            <div class="kv"><div class="k">Wallet Balance</div><div class="v">{{ $primaryWallet ? number_format((float) $primaryWallet->balance, 2) : '-' }}</div></div>
        </section>

        <section class="panel">
            <h3>Commission Settings</h3>
            <div class="kv"><div class="k">Default Admin Commission</div><div class="v">{{ $defaultCommission ? (float)$defaultCommission->admin_commission : 0 }}%</div></div>
            <div class="kv"><div class="k">Default Distributor Commission</div><div class="v">{{ $defaultCommission ? (float)$defaultCommission->distributor_commission : 0 }}%</div></div>
            <div class="kv"><div class="k">Override Active</div><div class="v">{{ $override && $override->is_active ? 'Yes' : 'No' }}</div></div>
            <div class="kv"><div class="k">Override Admin Commission</div><div class="v">{{ $override ? (float)$override->admin_commission : '-' }}{{ $override ? '%' : '' }}</div></div>
            <div class="kv"><div class="k">Override Distributor Commission</div><div class="v">{{ $override ? (float)$override->distributor_commission : '-' }}{{ $override ? '%' : '' }}</div></div>
            <div class="kv"><div class="k">Daily Limit</div><div class="v">{{ $dailyLimit ? number_format((float) $dailyLimit->max_amount, 2) : '-' }}</div></div>
            <div class="kv"><div class="k">Monthly Limit</div><div class="v">{{ $monthlyLimit ? number_format((float) $monthlyLimit->max_amount, 2) : '-' }}</div></div>
            <div class="kv"><div class="k">Per Transaction Limit</div><div class="v">{{ $perTxLimit ? number_format((float) $perTxLimit->max_amount, 2) : '-' }}</div></div>
        </section>
    </div>
</div>
</body>
</html>

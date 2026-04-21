<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Agent Profile</title>
    <style>
        :root { --blue:#1459c8; --panel:#ffffff; --text:#15233f; --muted:#5d6f8d; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif; color:var(--text); background:
            radial-gradient(circle at 12% 14%, rgba(91,156,255,.38) 0%, rgba(91,156,255,0) 36%),
            radial-gradient(circle at 88% 78%, rgba(106,173,255,.32) 0%, rgba(106,173,255,0) 38%),
            linear-gradient(180deg, #d8e8ff 0%, #c7ddff 45%, #b0d0ff 100%);
            background-attachment:fixed;
        }
        .wrap { max-width:1200px; margin:0 auto; padding:18px; }
        .top { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px; }
        .title { margin:0; font-size:30px; }
        .pill { background:#fff; border-radius:999px; padding:8px 12px; box-shadow:0 8px 20px rgba(14,35,77,.10); font-size:13px; }
        .btn { border:0; border-radius:8px; padding:9px 12px; cursor:pointer; color:#fff; background:#1b67d5; text-decoration:none; display:inline-block; }
        .btn.gray { background:#697892; }
        .btn.green { background:#1f8d4d; }
        .flash { margin:0 0 10px 0; padding:10px 12px; border-radius:10px; font-size:14px; }
        .flash.success { background:#e6f8ee; color:#13643a; }
        .flash.error { background:#ffe8e8; color:#a32525; }
        .panel { background:var(--panel); border-radius:12px; box-shadow:0 8px 20px rgba(31,56,98,.08); padding:14px; margin-bottom:12px; }
        .panel h3 { margin:0 0 12px; font-size:22px; }
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
        .field { display:flex; flex-direction:column; gap:5px; }
        .field label { font-size:13px; color:#263f69; font-weight:600; }
        .field input, .field select, .field textarea {
            width:100%; padding:10px 12px; border:1px solid #c7d5ea; border-radius:6px; font-size:14px; background:#fff; color:#1e325a;
        }
        .field input:focus, .field select:focus, .field textarea:focus { outline:none; border-color:#2180f2; box-shadow:0 0 0 2px rgba(33,128,242,.12); }
        .hint { font-size:12px; color:#6d7f9f; margin-top:4px; }
        .actions { display:flex; justify-content:flex-end; gap:8px; margin-top:10px; }
        .review-banner { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; padding:12px; border-radius:10px; background:#f8fbff; border:1px solid #d7e4f8; }
        .review-badge { padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700; background:#fff; border:1px solid #c7d5ea; color:#26406a; }
        .doc-grid { display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); gap:10px; margin-top:12px; }
        .doc-card { border:1px solid #dbe6f7; border-radius:10px; background:#f8fbff; padding:10px; }
        .doc-card strong { display:block; margin-bottom:8px; font-size:13px; color:#26406a; }
        .doc-card a { color:#1459c8; text-decoration:none; font-weight:600; }
        .doc-card img { width:100%; height:150px; object-fit:cover; border-radius:8px; border:1px solid #d9e5f7; background:#fff; }
        .quick-actions { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }
        @media(max-width:980px){ .grid-2{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <h1 class="title">Edit Agent Profile</h1>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <div class="pill">{{ $admin?->name }} | {{ $admin?->email }}</div>
            <a class="btn gray" href="{{ route('admin.users', ['section' => 'users']) }}">← Back to Users</a>
        </div>
    </div>

    @if(session('success'))<div class="flash success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="flash error">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="flash error">{{ $errors->first() }}</div>@endif

    @php
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

    <form method="post" action="{{ route('admin.users.update', $user->id) }}" enctype="multipart/form-data">
        @csrf

        <section class="panel">
            <h3>Personal Details</h3>
            <div class="grid-2">
                <div class="field"><label>First Name *</label><input name="name" value="{{ old('name', $user->name) }}" required></div>
                <div class="field"><label>Last Name</label><input name="last_name" value="{{ old('last_name', $user->last_name) }}"></div>
                <div class="field"><label>Email *</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
                <div class="field"><label>Mobile *</label><input name="phone" value="{{ old('phone', $user->phone) }}" maxlength="10" required></div>
                <div class="field"><label>Alternate Mobile</label><input name="alternate_mobile" value="{{ old('alternate_mobile', $user->alternate_mobile) }}" maxlength="10"></div>
                <div class="field"><label>Date of Birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth', $user->date_of_birth ? $user->date_of_birth->format('Y-m-d') : '') }}"></div>
                <div class="field"><label>Business Name</label><input name="business_name" value="{{ old('business_name', $user->business_name) }}"></div>
                <div class="field"><label>Role *</label>
                    <select name="role" required>
                        @php $selectedRole = old('role', $user->role); @endphp
                        <option value="admin" {{ $selectedRole==='admin'?'selected':'' }}>Admin</option>
                        <option value="master_distributor" {{ $selectedRole==='master_distributor'?'selected':'' }}>Master Distributor</option>
                        <option value="super_distributor" {{ $selectedRole==='super_distributor'?'selected':'' }}>Super Distributor</option>
                        <option value="distributor" {{ $selectedRole==='distributor'?'selected':'' }}>Distributor</option>
                        <option value="retailer" {{ $selectedRole==='retailer'?'selected':'' }}>Retailer</option>
                    </select>
                </div>
                <div class="field" style="grid-column:1/-1;"><label>Parent User</label>
                    @php $selectedParent = old('distributor_id', $user->distributor_id); @endphp
                    <select name="distributor_id">
                        <option value="">Select Parent</option>
                        @foreach($masterDistributors as $md)
                            <option value="{{ $md->id }}" {{ (string)$selectedParent===(string)$md->id ? 'selected' : '' }}>{{ $md->name }} (master)</option>
                        @endforeach
                        @foreach($superDistributors as $sd)
                            <option value="{{ $sd->id }}" {{ (string)$selectedParent===(string)$sd->id ? 'selected' : '' }}>{{ $sd->name }} (super)</option>
                        @endforeach
                        @foreach($distributors as $d)
                            <option value="{{ $d->id }}" {{ (string)$selectedParent===(string)$d->id ? 'selected' : '' }}>{{ $d->name }} (distributor)</option>
                        @endforeach
                    </select>
                    <div class="hint">Super Distributor → parent must be Master, Distributor → parent must be Super, Retailer → parent must be Distributor.</div>
                </div>
                <div class="field" style="grid-column:1/-1;"><label>Address</label><textarea name="address" rows="2">{{ old('address', $user->address) }}</textarea></div>
                <div class="field"><label>City</label><input name="city" value="{{ old('city', $user->city) }}"></div>
                <div class="field"><label>State</label><input name="state" value="{{ old('state', $user->state) }}"></div>
                <div class="field" style="grid-column:1/-1;"><label>Profile Photo</label><input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp"></div>
            </div>
        </section>

        <section class="panel">
            <h3>eKYC Details</h3>
            <div class="review-banner">
                <div>
                    <div style="font-weight:700; color:#26406a;">Dedicated eKYC Review</div>
                    <div class="hint" style="margin-top:2px;">Review uploaded retailer documents here and update the status without changing the rest of the user flow.</div>
                </div>
                <div class="review-badge">{{ strtoupper($user->kyc_status ?: 'PENDING') }}</div>
            </div>
            <div class="grid-2">
                <div class="field"><label>KYC ID Number</label><input name="kyc_id_number" value="{{ old('kyc_id_number', $user->kyc_id_number) }}"></div>
                <div class="field"><label>Document Type</label><input name="kyc_document_type" value="{{ old('kyc_document_type', $user->kyc_document_type) }}"></div>
                <div class="field"><label>KYC Status</label>
                    @php $selectedKycStatus = old('kyc_status', $user->kyc_status ?: 'pending'); @endphp
                    <select name="kyc_status">
                        <option value="pending" {{ $selectedKycStatus==='pending'?'selected':'' }}>Pending</option>
                        <option value="approved" {{ $selectedKycStatus==='approved'?'selected':'' }}>Approved</option>
                        <option value="rejected" {{ $selectedKycStatus==='rejected'?'selected':'' }}>Rejected</option>
                        <option value="submitted" {{ $selectedKycStatus==='submitted'?'selected':'' }}>Submitted</option>
                    </select>
                </div>
                <div class="field"><label>KYC Photo</label><input type="file" name="kyc_photo" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
                <div class="field"><label>Address Proof Front</label><input type="file" name="address_proof_front" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
                <div class="field"><label>Address Proof Back</label><input type="file" name="address_proof_back" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>
            </div>
            <div class="quick-actions">
                <button class="btn green" type="submit" onclick="this.form.elements['kyc_status'].value='approved'">Approve eKYC</button>
                <button class="btn gray" type="submit" onclick="this.form.elements['kyc_status'].value='rejected'">Reject eKYC</button>
            </div>
            <div class="doc-grid">
                @foreach([
                    'KYC Photo' => ['path' => $user->kyc_photo_path, 'url' => $docLink($user->kyc_photo_path)],
                    'Address Proof Front' => ['path' => $user->address_proof_front_path, 'url' => $docLink($user->address_proof_front_path)],
                    'Address Proof Back' => ['path' => $user->address_proof_back_path, 'url' => $docLink($user->address_proof_back_path)],
                ] as $label => $doc)
                    @if($doc['url'])
                        <div class="doc-card">
                            <strong>{{ $label }}</strong>
                            @if($isImage($doc['path']))
                                <a href="{{ $doc['url'] }}" target="_blank"><img src="{{ $doc['url'] }}" alt="{{ $label }}"></a>
                            @else
                                <a href="{{ $doc['url'] }}" target="_blank">Open {{ $label }}</a>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </section>

        <section class="panel">
            <h3>Bank Account Details</h3>
            <div class="grid-2">
                <div class="field"><label>Account Holder Name</label><input name="bank_account_name" value="{{ old('bank_account_name', $user->bank_account_name) }}"></div>
                <div class="field"><label>Account Number</label><input name="bank_account_number" value="{{ old('bank_account_number', $user->bank_account_number) }}"></div>
                <div class="field"><label>IFSC Code</label><input name="bank_ifsc_code" value="{{ old('bank_ifsc_code', $user->bank_ifsc_code) }}"></div>
                <div class="field"><label>Bank Name</label><input name="bank_name" value="{{ old('bank_name', $user->bank_name) }}"></div>
            </div>
        </section>

        <section class="panel">
            <h3>Commission Settings</h3>
            @php
                $overrideAdmin = old('admin_commission', optional($user->commissionOverride)->admin_commission);
                $overrideDistributor = old('distributor_commission', optional($user->commissionOverride)->distributor_commission);
            @endphp
            <div class="grid-2">
                <div class="field"><label>Default Admin Commission</label><input value="{{ $defaultCommission ? (float)$defaultCommission->admin_commission : 0 }}%" disabled></div>
                <div class="field"><label>Default Distributor Commission</label><input value="{{ $defaultCommission ? (float)$defaultCommission->distributor_commission : 0 }}%" disabled></div>
                <div class="field"><label>Override Admin Commission (%)</label><input type="number" step="0.01" min="0" max="100" name="admin_commission" value="{{ $overrideAdmin }}"></div>
                <div class="field"><label>Override Distributor Commission (%)</label><input type="number" step="0.01" min="0" max="100" name="distributor_commission" value="{{ $overrideDistributor }}"></div>
            </div>
        </section>

        <div class="actions">
            <a class="btn gray" href="{{ route('admin.users', ['section' => 'users']) }}">Cancel</a>
            <button class="btn green" type="submit">Save & Submit</button>
        </div>
    </form>
</div>
</body>
</html>

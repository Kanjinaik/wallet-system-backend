            @if($userSection === 'add-user')
                <section class="panel">
                    <div class="users-toolbar" style="margin-bottom:18px;">
                        <h3 style="margin:0;">Add User</h3>
                        <div class="users-toolbar-right" style="min-width:280px;">
                            <select id="add-user-role-quick-select">
                                <option value="">Select Role</option>
                                <option value="master_distributor" {{ old('role')==='master_distributor'?'selected':'' }}>Master Distributor</option>
                                <option value="super_distributor" {{ old('role')==='super_distributor'?'selected':'' }}>Super Distributor</option>
                                <option value="distributor" {{ old('role')==='distributor'?'selected':'' }}>Distributor</option>
                                <option value="retailer" {{ old('role')==='retailer'?'selected':'' }}>Retailer</option>
                            </select>
                        </div>
                    </div>
                    <div class="wizard-steps" id="add-user-steps">
                        <span class="wizard-step active" data-step="1">Personal Information</span>
                        <span class="wizard-divider"></span>
                        <span class="wizard-step" data-step="2">User eKYC</span>
                        <span class="wizard-divider"></span>
                        <span class="wizard-step" data-step="3">Bank Information</span>
                        <span class="wizard-divider"></span>
                        <span class="wizard-step" data-step="4">Commission Settings</span>
                    </div>

                    <form id="add-user-wizard-form" method="post" action="{{ route('admin.users.create') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="hidden" name="role" value="{{ old('role') }}">

                        <div class="wizard-section" data-step="1">
                            <h4>Personal Information</h4>
                            <div class="wizard-grid-2">
                                <div>
                                    <label class="w-label">First Name *</label>
                                    <input name="name" placeholder="First Name" value="{{ old('name') }}" required>
                                </div>
                                <div>
                                    <label class="w-label">Last Name *</label>
                                    <input name="last_name" placeholder="Last Name" value="{{ old('last_name') }}">
                                </div>

                                <div>
                                    <label class="w-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}">
                                </div>
                                <div style="display:flex;flex-direction:column;gap:4px;">
                                    <label class="w-label">Email Address *</label>
                                    <input type="email" name="email" placeholder="Email Address" value="{{ old('email') }}" required>
                                    @error('email')
                                        <span style="color:#bf2d44;font-size:12px;">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div>
                                    <label class="w-label">Mobile Number *</label>
                                    <input name="phone" placeholder="Mobile Number" value="{{ old('phone') }}" required inputmode="numeric" maxlength="10" pattern="[0-9]{10}">
                                </div>
                                <div>
                                    <label class="w-label">Alternative Mobile Number</label>
                                    <input name="alternate_mobile" placeholder="Alternative Mobile Number" value="{{ old('alternate_mobile') }}" inputmode="numeric" maxlength="10" pattern="[0-9]{10}">
                                </div>

                                <div>
                                    <label class="w-label">Business Name *</label>
                                    <input name="business_name" placeholder="Business Name" value="{{ old('business_name') }}">
                                </div>
                                <div>
                                    <label class="w-label">Company Name</label>
                                    <input name="company_name" placeholder="Company Name (optional)" value="{{ old('company_name') }}">
                                </div>

                                <div>
                                    <label class="w-label">Address *</label>
                                    <input name="address" placeholder="Address" value="{{ old('address') }}">
                                </div>
                                <div>
                                    <label class="w-label">GST Number</label>
                                    <input name="gst_number" placeholder="GST Number" value="{{ old('gst_number') }}">
                                </div>

                                <div>
                                    <label class="w-label">State *</label>
                                    <input name="state" placeholder="State" value="{{ old('state') }}">
                                </div>
                                <div>
                                    <label class="w-label">City *</label>
                                    <input name="city" placeholder="City" value="{{ old('city') }}">
                                </div>

                                <div>
                                    <label class="w-label">Pincode *</label>
                                    <input name="pincode" placeholder="Pincode (optional)" value="{{ old('pincode') }}">
                                </div>
                                <div>
                                    <label class="w-label">Upload Photo</label>
                                    <div class="upload-box">
                                        <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp">
                                        <div class="upload-note">Upload Photo, Max size 2MB preferred</div>
                                    </div>
                                </div>

                                <div>
                                    <label class="w-label">Password *</label>
                                    <input type="password" name="password" placeholder="Password (min 8)" required>
                                </div>
                                <div>
                                    <label class="w-label">Confirm Password *</label>
                                    <input type="password" name="password_confirmation" placeholder="Confirm Password" required>
                                </div>
                            </div>

                            <div class="wizard-actions">
                                <div class="wizard-actions-left">
                                    <span class="wizard-status" data-wizard-status>Draft not saved yet.</span>
                                </div>
                                <div class="wizard-actions-right">
                                    <button class="btn ghost" type="button" data-save-step>Save Progress</button>
                                    <button class="btn" type="button" data-next-step="2">Save & Next</button>
                                </div>
                            </div>
                        </div>

                        <div class="wizard-section hidden-step" data-step="2">
                            <h4>User eKYC</h4>
                            <div class="wizard-grid-2">
                                <div>
                                    <label class="w-label">Document Type *</label>
                                    <select name="kyc_document_type">
                                        <option value="">Document Type</option>
                                        <option value="pan" {{ old('kyc_document_type')==='pan'?'selected':'' }}>PAN Card</option>
                                        <option value="aadhaar" {{ old('kyc_document_type')==='aadhaar'?'selected':'' }}>Aadhaar Card</option>
                                        <option value="other" {{ old('kyc_document_type')==='other'?'selected':'' }}>Other</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="w-label">Document Number *</label>
                                    <input name="kyc_id_number" placeholder="Document Number (Aadhaar / PAN)" value="{{ old('kyc_id_number') }}">
                                </div>

                                <div>
                                    <label class="w-label">Upload Document Front *</label>
                                    <div class="upload-box">
                                        <input type="file" name="address_proof_front" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                        <div class="upload-note">Upload front side</div>
                                    </div>
                                </div>
                                <div>
                                    <label class="w-label">Upload Document Back *</label>
                                    <div class="upload-box">
                                        <input type="file" name="address_proof_back" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                        <div class="upload-note">Upload back side</div>
                                    </div>
                                </div>

                                <div>
                                    <label class="w-label">Second Document Type</label>
                                    <select name="secondary_document_type">
                                        <option value="pan" {{ old('secondary_document_type', 'pan')==='pan'?'selected':'' }}>PAN Card</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="w-label">Second Document Number</label>
                                    <input name="pan_number" placeholder="PAN Number" value="{{ old('pan_number') }}">
                                </div>

                                <div>
                                    <label class="w-label">Upload Second Document Front</label>
                                    <div class="upload-box">
                                        <input type="file" name="pan_proof_front" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                        <div class="upload-note">Upload PAN front side</div>
                                    </div>
                                </div>
                                <div>
                                    <label class="w-label">Upload Second Document Back</label>
                                    <div class="upload-box">
                                        <input type="file" name="pan_proof_back" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                        <div class="upload-note">Upload PAN back side</div>
                                    </div>
                                </div>

                                <div style="grid-column:1/-1;">
                                    <label class="w-label">Upload KYC Document (optional)</label>
                                    <div class="upload-box">
                                        <input type="file" name="kyc_photo" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-actions">
                                <div class="wizard-actions-left">
                                    <button class="btn gray" type="button" data-prev-step="1">Back</button>
                                </div>
                                <div class="wizard-actions-right">
                                    <button class="btn ghost" type="button" data-save-step>Save Progress</button>
                                    <button class="btn" type="button" data-next-step="3">Save & Next</button>
                                </div>
                            </div>
                        </div>

                        <div class="wizard-section hidden-step" data-step="3">
                            <h4>Bank Information</h4>
                            <div class="wizard-grid-2">
                                <div style="grid-column:1/-1;">
                                    <label class="w-label">Account Holder Name *</label>
                                    <input name="bank_account_name" placeholder="Account Holder Name" value="{{ old('bank_account_name') }}">
                                </div>
                                <div>
                                    <label class="w-label">Account Number *</label>
                                    <input name="bank_account_number" placeholder="Account Number" value="{{ old('bank_account_number') }}">
                                </div>
                                <div>
                                    <label class="w-label">Bank Name *</label>
                                    <input name="bank_name" placeholder="Bank Name" value="{{ old('bank_name') }}">
                                </div>
                                <div>
                                    <label class="w-label">IFSC Code *</label>
                                    <input name="bank_ifsc_code" placeholder="IFSC Code" value="{{ old('bank_ifsc_code') }}">
                                </div>
                                <div>
                                    <label class="w-label">Branch Name *</label>
                                    <input name="branch_name" placeholder="Branch Name (optional)" value="{{ old('branch_name') }}">
                                </div>
                                <div style="grid-column:1/-1;">
                                    <label class="w-label">Upload Bank Document</label>
                                    <div class="upload-box">
                                        <input type="file" name="bank_document" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                        <div class="upload-note">Upload canceled cheque/passbook</div>
                                    </div>
                                </div>
                            </div>

                            <div class="wizard-actions">
                                <div class="wizard-actions-left">
                                    <button class="btn gray" type="button" data-prev-step="2">Back</button>
                                </div>
                                <div class="wizard-actions-right">
                                    <button class="btn ghost" type="button" data-save-step>Save Progress</button>
                                    <button class="btn" type="button" data-next-step="4">Save & Next</button>
                                </div>
                            </div>
                        </div>

                        <div class="wizard-section hidden-step" data-step="4">
                            <h4>Commission Settings</h4>
                            <div class="wizard-grid-2">
                                <div>
                                    <label class="w-label">Commission Rate (%) *</label>
                                    <input type="number" step="0.01" min="0" max="100" name="admin_commission" placeholder="Admin Commission (%)" value="{{ old('admin_commission') }}">
                                </div>
                                <div>
                                    <label class="w-label">Mobility Check</label>
                                    <select name="mobility_check">
                                        <option value="low">Mobility Check - Low</option>
                                        <option value="medium">Mobility Check - Medium</option>
                                        <option value="high">Mobility Check - High</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="w-label">Opening Balance</label>
                                    <input type="number" step="0.01" min="0" name="opening_balance" placeholder="Opening Balance" value="{{ old('opening_balance') }}">
                                </div>
                            </div>

                            <p class="note-line"><strong>Note:</strong> Commission settings affect the distributor earnings and limits.</p>
                            <div class="wizard-actions">
                                <div class="wizard-actions-left">
                                    <button class="btn gray" type="button" data-prev-step="3">Back</button>
                                </div>
                                <div class="wizard-actions-right">
                                    <button class="btn ghost" type="button" data-save-step>Save Progress</button>
                                    <button class="btn green" type="submit">Create User</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <p class="tiny">After creation, user is redirected to List of Users.</p>
                </section>
            @elseif($userSection === 'roles')
                <section class="cards">
                    <article class="card c6"><span>Master Distributors</span><strong>{{ $masterDistributors->count() }}</strong></article>
                    <article class="card c6"><span>Super Distributors</span><strong>{{ $superDistributors->count() }}</strong></article>
                    <article class="card c5"><span>Distributors</span><strong>{{ $distributors->count() }}</strong></article>
                    <article class="card c2"><span>Retailers</span><strong>{{ $retailers->count() }}</strong></article>
                    <article class="card c1"><span>Total Active</span><strong>{{ $masterDistributors->where('is_active', true)->count() + $superDistributors->where('is_active', true)->count() + $distributors->where('is_active', true)->count() + $retailers->where('is_active', true)->count() }}</strong></article>
                </section>
                <section class="panel">
                    <h3>Role Matrix</h3>
                    <table>
                        <thead><tr><th>Role</th><th>Total Users</th><th>Active Users</th><th>Inactive Users</th></tr></thead>
                        <tbody>
                            <tr><td>Master Distributor</td><td>{{ $masterDistributors->count() }}</td><td>{{ $masterDistributors->where('is_active', true)->count() }}</td><td>{{ $masterDistributors->where('is_active', false)->count() }}</td></tr>
                            <tr><td>Super Distributor</td><td>{{ $superDistributors->count() }}</td><td>{{ $superDistributors->where('is_active', true)->count() }}</td><td>{{ $superDistributors->where('is_active', false)->count() }}</td></tr>
                            <tr><td>Distributor</td><td>{{ $distributors->count() }}</td><td>{{ $distributors->where('is_active', true)->count() }}</td><td>{{ $distributors->where('is_active', false)->count() }}</td></tr>
                            <tr><td>Retailer</td><td>{{ $retailers->count() }}</td><td>{{ $retailers->where('is_active', true)->count() }}</td><td>{{ $retailers->where('is_active', false)->count() }}</td></tr>
                        </tbody>
                    </table>
                </section>
            @else
                @php
                    $allManagedUsers = $masterDistributors
                        ->concat($superDistributors)
                        ->concat($distributors)
                        ->concat($retailers)
                        ->sortBy('id')
                        ->values();
                @endphp
                <section class="panel">
                    <div class="top" style="margin-bottom:0;">
                        <h3 style="margin:0;">List Of Users</h3>
                        <div class="users-toolbar-right">
                            <div class="users-export-wrap">
                                <button id="users-export-btn" class="btn gray" type="button">⎙ Export ▾</button>
                                <div class="users-export-menu" id="users-export-menu">
                                    <button type="button" id="users-export-csv">Export CSV</button>
                                </div>
                            </div>
                            <a class="btn red" href="{{ route('admin.users', ['section' => 'add-user']) }}" style="text-decoration:none;display:inline-block;">⊕ Add User</a>
                        </div>
                    </div>
                </section>
                <section class="panel">
                    <div class="users-toolbar" style="margin-bottom:10px;">
                        <div class="users-toolbar-left">
                            <label>Show
                                <select id="users-page-size">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                entries
                            </label>
                        </div>
                        <div class="users-toolbar-right">
                            <label>Search:
                                <input id="users-search" type="text" placeholder="Name, email, role, mobile">
                            </label>
                        </div>
                    </div>
                    <table class="users-table" id="users-table">
                        <thead><tr><th>S. No <span class="sort">↕</span></th><th>Photo <span class="sort">↕</span></th><th>Name <span class="sort">↕</span></th><th>Email <span class="sort">↕</span></th><th>Password <span class="sort">↕</span></th><th>Agent ID <span class="sort">↕</span></th><th>Role <span class="sort">↕</span></th><th>Mobile <span class="sort">↕</span></th><th>Status <span class="sort">↕</span></th><th>Action <span class="sort">↕</span></th></tr></thead>
                        <tbody id="users-table-body">
                            @forelse($allManagedUsers as $index => $u)
                                @php
                                    $roleCodeMap = [
                                        'master_distributor' => 'MD',
                                        'super_distributor' => 'SD',
                                        'distributor' => 'DT',
                                        'retailer' => 'RT',
                                        'admin' => 'AD',
                                    ];
                                    $roleCode = $roleCodeMap[$u->role] ?? 'US';
                                    $agentCode = 'XT' . $roleCode . str_pad((string)$u->id, 4, '0', STR_PAD_LEFT);
                                    $photoUrl = $toMediaUrl($u->profile_photo_path);
                                    $businessName = trim((string)($u->business_name ?? ''));
                                @endphp
                                @php
                                    $searchIndex = strtolower(implode(' ', array_filter([
                                        $u->name,
                                        $u->last_name,
                                        $u->email,
                                        $agentCode,
                                        $u->role,
                                        $u->phone,
                                    ])));
                                    $parentUser = $u->distributor;
                                    $userWallet = $u->wallets->first();
                                @endphp
                                <tr data-search="{{ $searchIndex }}">
                                    <td class="js-serial">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="user-avatar">
                                            @if($photoUrl)
                                                <img src="{{ $photoUrl }}" alt="{{ $u->name }}">
                                            @else
                                                <span>◌</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <div class="name-block">
                                            <span class="name-main">{{ $u->name }}</span>
                                            @if($businessName !== '')
                                                <span class="name-sub">{{ $businessName }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $u->email }}</td>
                                    <td>{{ $u->plain_password ?: 'Protected' }}</td>
                                    <td>{{ $agentCode }}</td>
                                    <td><span class="role-pill">{{ ucwords(str_replace('_',' ',$u->role)) }}</span></td>
                                    <td>{{ $u->phone ?: '-' }}</td>
                                    <td><span class="status-pill {{ $u->is_active ? '' : 'inactive' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span></td>
                                    <td>
                                        <div class="action-icons">
                                            <button type="button" class="action-icon action-filter js-history-filter" data-user-id="{{ $u->id }}" data-user-name="{{ $u->name }}" data-user-role="{{ $u->role }}" title="Filter History">⚲</button>
                                            <a class="action-icon action-view" href="{{ route('admin.users.profile', $u->id) }}" title="View">👁</a>
                                            <a class="action-icon action-edit" href="{{ route('admin.users.edit', $u->id) }}" title="Edit">✎</a>
                                            <form method="post" action="{{ route('admin.users.toggle',$u->id) }}">@csrf<button class="action-icon action-toggle" title="{{ $u->is_active ? 'Deactivate' : 'Activate' }}">{{ $u->is_active ? '⏸' : '▶' }}</button></form>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="user-row-details" id="user-details-{{ $u->id }}" style="display:none;">
                                    <td colspan="10">
                                        <strong>Profile Details:</strong>
                                        Agent ID: {{ $agentCode }} |
                                        Name: {{ trim(($u->name ?? '') . ' ' . ($u->last_name ?? '')) ?: '-' }} |
                                        Email: {{ $u->email ?: '-' }} |
                                        Role: {{ ucwords(str_replace('_',' ',$u->role)) }} |
                                        Parent: {{ $parentUser?->name ?: '-' }} |
                                        Mobile: {{ $u->phone ?: '-' }} |
                                        Alternate Mobile: {{ $u->alternate_mobile ?: '-' }} |
                                        Business Name: {{ $u->business_name ?: '-' }} |
                                        DOB: {{ $u->date_of_birth ? $u->date_of_birth->format('d-m-Y') : '-' }} |
                                        Address: {{ $u->address ?: '-' }} |
                                        City: {{ $u->city ?: '-' }} |
                                        State: {{ $u->state ?: '-' }} |
                                        KYC ID Number: {{ $u->kyc_id_number ?: '-' }} |
                                        Bank Account Name: {{ $u->bank_account_name ?: '-' }} |
                                        Bank Account Number: {{ $u->bank_account_number ?: '-' }} |
                                        IFSC: {{ $u->bank_ifsc_code ?: '-' }} |
                                        Bank Name: {{ $u->bank_name ?: '-' }} |
                                        Status: {{ $u->is_active ? 'Active' : 'Inactive' }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="users-empty">No users found</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="users-pagination">
                        <div class="tiny" id="users-page-info">Showing 0 to 0 of 0 entries</div>
                        <div class="pager">
                            <button type="button" id="users-prev">Previous</button>
                            <button type="button" id="users-next">Next</button>
                        </div>
                    </div>
                </section>

                <div class="password-modal-backdrop" id="password-modal-backdrop">
                    <div class="password-modal">
                        <h4 id="password-modal-title">Edit User</h4>
                        <form method="post" id="password-change-form">
                            @csrf
                            <div class="form-grid-2">
                                <input type="text" name="name" id="edit-user-name" placeholder="Name" required>
                                <input type="email" name="email" id="edit-user-email" placeholder="Email" required>
                                <input type="text" name="phone" id="edit-user-phone" placeholder="Mobile Number" minlength="10" maxlength="10" pattern="[0-9]{10}" required>
                            </div>
                            <div class="actions">
                                <button class="btn gray" type="button" id="password-cancel">Cancel</button>
                                <button class="btn" type="submit">Update User</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="password-modal-backdrop" id="history-modal-backdrop">
                    <div class="password-modal history-modal">
                        <h4 id="history-modal-title">Transaction History</h4>
                        <div class="history-filter-grid">
                            <div>
                                <label for="history-filter-name">Name</label>
                                <input type="text" id="history-filter-name" placeholder="Search by name, ref, details">
                            </div>
                            <div>
                                <label for="history-filter-date">Date</label>
                                <input type="date" id="history-filter-date">
                            </div>
                            <div>
                                <label for="history-filter-type">Transaction History</label>
                                <select id="history-filter-type"></select>
                            </div>
                        </div>
                        <div class="history-feedback tiny" id="history-feedback">Select a user to view history.</div>
                        <div class="history-table-wrap">
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Reference</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody id="history-table-body">
                                    <tr><td colspan="6" class="users-empty">No history loaded</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="actions">
                            <button class="btn gray" type="button" id="history-clear">Clear</button>
                            <button class="btn gray" type="button" id="history-cancel">Close</button>
                        </div>
                    </div>
                </div>
            @endif


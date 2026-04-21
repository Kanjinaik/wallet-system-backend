            <section class="panel">
                <h3>Profile Photo</h3>
                <div class="admin-photo-card">
                    <div class="admin-photo-left">
                        <div class="admin-photo-preview">
                            @if($adminPhotoUrl)
                                <img src="{{ $adminPhotoUrl }}" alt="Admin profile photo">
                            @else
                                <span>{{ $adminInitials }}</span>
                            @endif
                        </div>
                        <div class="tiny">Upload JPG, JPEG, PNG, or WEBP (max 5MB)</div>
                    </div>
                    <form class="admin-photo-form" method="post" action="{{ route('admin.profile.photo') }}" enctype="multipart/form-data">
                        @csrf
                        <input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp" required>
                        <button class="btn" type="submit">Update Photo</button>
                    </form>
                </div>
            </section>
            <section class="row3">
                <article class="panel"><h3>Name</h3><p>{{ $admin?->name }}</p></article>
                <article class="panel"><h3>Email</h3><p>{{ $admin?->email }}</p></article>
                <article class="panel"><h3>Phone</h3><p>{{ $admin?->phone ?: '-' }}</p></article>
                <article class="panel"><h3>DOB</h3><p>{{ $admin?->date_of_birth ? $admin->date_of_birth->format('d-m-Y') : '-' }}</p></article>
                <article class="panel"><h3>Main Wallet</h3><p>₹{{ number_format((float)($adminMainWallet?->balance ?? 0),2) }}</p></article>
                <article class="panel"><h3>Status</h3><p>{{ $admin?->is_active ? 'Active' : 'Inactive' }}</p></article>
            </section>

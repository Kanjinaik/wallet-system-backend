<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'last_name',
        'email',
        'password',
        'plain_password',
        'phone',
        'alternate_mobile',
        'business_name',
        'address',
        'city',
        'state',
        'date_of_birth',
        'profile_photo_path',
        'kyc_id_number',
        'pan_number',
        'pan_proof_front_path',
        'pan_proof_back_path',
        'kyc_document_type',
        'kyc_photo_path',
        'address_proof_front_path',
        'address_proof_back_path',
        'kyc_selfie_path',
        'kyc_liveness_verified',
        'role',
        'distributor_id',
        'is_active',
        'bank_account_name',
        'bank_account_number',
        'bank_ifsc_code',
        'bank_name',
        'kyc_document_path',
        'kyc_status',
        'withdraw_otp_code',
        'withdraw_otp_expires_at',
    ];

    protected $hidden = [
        'password',
        'plain_password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'date_of_birth' => 'date',
        'distributor_id' => 'integer',
        'is_active' => 'boolean',
        'kyc_liveness_verified' => 'boolean',
        'withdraw_otp_expires_at' => 'datetime',
    ];

    protected $appends = [
        'agent_id',
        'profile_photo_url',
    ];

    public function getAgentIdAttribute(): string
    {
        $roleCodeMap = [
            'master_distributor' => 'MD',
            'super_distributor' => 'SD',
            'distributor' => 'DT',
            'retailer' => 'RT',
            'admin' => 'AD',
        ];

        $roleCode = $roleCodeMap[$this->role] ?? 'US';

        return 'XT' . $roleCode . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (!$this->profile_photo_path) {
            return null;
        }

        if (Str::startsWith($this->profile_photo_path, ['http://', 'https://'])) {
            return $this->profile_photo_path;
        }

        return Storage::disk('public')->url($this->profile_photo_path);
    }

    public static function generateDefaultProfilePhotoPath(string $name): string
    {
        $initials = collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');

        $displayInitials = $initials !== '' ? $initials : 'U';

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240" viewBox="0 0 240 240" role="img" aria-label="Profile avatar">
  <defs>
    <linearGradient id="avatarGradient" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#1d4ed8"/>
      <stop offset="100%" stop-color="#60a5fa"/>
    </linearGradient>
  </defs>
  <rect width="240" height="240" rx="120" fill="url(#avatarGradient)"/>
  <text x="120" y="136" text-anchor="middle" font-family="Arial, sans-serif" font-size="84" font-weight="700" fill="#ffffff">{$displayInitials}</text>
</svg>
SVG;

        $path = 'users/profile-photo/default-' . Str::uuid()->toString() . '.svg';
        Storage::disk('public')->put($path, $svg);

        return $path;
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function walletLimits()
    {
        return $this->hasMany(WalletLimit::class);
    }

    public function scheduledTransfers()
    {
        return $this->hasMany(ScheduledTransfer::class);
    }

    public function distributor()
    {
        return $this->belongsTo(User::class, 'distributor_id');
    }

    public function retailers()
    {
        return $this->hasMany(User::class, 'distributor_id');
    }

    public function commissionOverride()
    {
        return $this->hasOne(CommissionOverride::class);
    }

    public function masterDistributor()
    {
        return $this->belongsTo(User::class, 'distributor_id');
    }

    public function distributors()
    {
        return $this->hasMany(User::class, 'distributor_id')->where('role', 'distributor');
    }

    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function userCommission()
    {
        return $this->hasOne(UserCommission::class);
    }
}

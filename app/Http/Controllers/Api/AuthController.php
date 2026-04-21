<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    private const FRONTEND_DISABLED_MESSAGE = 'Admin stop the server contact to admin';

    public function register(Request $request)
    {
        try {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'role' => 'nullable|in:user,admin,master_distributor,super_distributor,distributor,retailer',
        ];

        // Keep backward compatibility if migration has not run yet.
        if (Schema::hasColumn('users', 'distributor_id')) {
            $rules['distributor_id'] = [
                'nullable',
                'required_if:role,retailer',
                Rule::exists('users', 'id')->where(function ($query) {
                    $query->where('role', 'distributor');
                }),
            ];
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'plain_password' => null,
            'phone' => $request->phone,
            'role' => $request->input('role', 'user'),
            'profile_photo_path' => User::generateDefaultProfilePhotoPath($request->name),
        ];

        if (Schema::hasColumn('users', 'distributor_id')) {
            $userData['distributor_id'] = $request->input('distributor_id');
        }

        $user = User::create($userData);

        $walletType = in_array($user->role, ['master_distributor', 'super_distributor', 'distributor', 'retailer'], true) ? 'sub' : 'main';
        $walletName = $walletType === 'main' ? 'Main Wallet' : ucfirst($user->role) . ' Wallet';

        // Create default wallet for the user based on role hierarchy.
        $user->wallets()->create([
            'name' => $walletName,
            'type' => $walletType,
            'balance' => 0,
        ]);

        // Set default limits
        $user->walletLimits()->createMany([
            [
                'limit_type' => 'daily',
                'max_amount' => 500000,
                'reset_date' => now()->toDateString(),
            ],
            [
                'limit_type' => 'monthly',
                'max_amount' => 500000,
                'reset_date' => now()->startOfMonth()->toDateString(),
            ],
            [
                'limit_type' => 'per_transaction',
                'max_amount' => 500000,
            ],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'User registered successfully'
        ], 201);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection/configuration error. Please verify MySQL credentials in backend_wallet/.env.',
                'error_code' => $e->getCode(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
        $validator = Validator::make($request->all(), [
            'agent_id' => 'nullable|string|max:50',
            'email' => 'nullable|string|max:255',
            'login' => 'nullable|string|max:255',
            'password' => 'required',
            'role' => 'nullable|in:user,admin,master_distributor,super_distributor,distributor,retailer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $identifier = trim((string) ($request->agent_id ?? $request->login ?? $request->email ?? ''));
        if ($identifier === '') {
            return response()->json([
                'errors' => [
                    'agent_id' => ['Agent ID is required.'],
                ],
            ], 422);
        }

        $user = $this->resolveLoginUser($identifier);

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$this->isFrontendEnabled() && $user->role !== 'admin') {
            return response()->json(['message' => self::FRONTEND_DISABLED_MESSAGE], 403);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is deactivated'], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Login successful'
        ]);
        } catch (QueryException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection/configuration error. Please verify MySQL credentials in backend_wallet/.env.',
                'error_code' => $e->getCode(),
            ], 500);
        }
    }

    public function frontendStatus()
    {
        $enabled = $this->isFrontendEnabled();

        return response()->json([
            'frontend_enabled' => $enabled,
            'message' => $enabled ? 'Frontend is active' : self::FRONTEND_DISABLED_MESSAGE,
        ]);
    }

    private function resolveLoginUser(string $identifier): ?User
    {
        $normalized = trim($identifier);
        if ($normalized === '') {
            return null;
        }

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $normalized)->first();
        }

        $normalized = strtoupper(str_replace(' ', '', $normalized));
        if (preg_match('/^XT[A-Z]{2}(\d+)$/', $normalized, $matches) !== 1) {
            return null;
        }

        $user = User::find((int) $matches[1]);
        if (!$user) {
            return null;
        }

        return strtoupper($user->agent_id) === $normalized ? $user : null;
    }

    private function isFrontendEnabled(): bool
    {
        return AdminSetting::getValue('sys_frontend_enabled', '1') === '1';
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function distributors()
    {
        $distributors = User::where('role', 'distributor')
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json($distributors);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Do not disclose whether email exists in production-style response.
        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'If this email exists, a reset token has been generated.'
            ]);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        if ($this->shouldExposeDevelopmentSecrets()) {
            return response()->json([
                'success' => true,
                'message' => 'Reset token generated successfully.',
                'reset_token' => $token,
                'email' => $user->email,
            ]);
        }

        if (!$this->sendPasswordResetToken($user, $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to send reset instructions right now. Please try again later or contact support.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'message' => 'If this email exists, reset instructions have been sent.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $reset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$reset || !Hash::check($request->token, $reset->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset token.',
            ], 422);
        }

        $createdAt = Carbon::parse($reset->created_at);
        if ($createdAt->lt(now()->subMinutes(30))) {
            return response()->json([
                'success' => false,
                'message' => 'Reset token expired. Please request a new one.',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $user->password = Hash::make($request->password);
        $user->plain_password = null;
        $user->save();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successful. Please login.',
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user()->fresh());
    }

    private function shouldExposeDevelopmentSecrets(): bool
    {
        return app()->environment('local') || (bool) config('app.debug');
    }

    private function sendPasswordResetToken(User $user, string $token): bool
    {
        try {
            Mail::raw(
                "Your password reset token is: {$token}\nThis token will expire in 30 minutes.",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject(config('app.name', 'Wallet System') . ' Password Reset Token');
                }
            );

            return true;
        } catch (\Throwable $exception) {
            Log::error('Failed to send password reset token email.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}

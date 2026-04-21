<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminWebAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check() && Auth::user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth', ['mode' => 'login']);
    }

    public function showRegister()
    {
        if (!$this->registrationEnabled()) {
            return redirect()->route('admin.login.form')
                ->with('error', 'Admin registration is disabled.');
        }

        if (Auth::check() && Auth::user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth', ['mode' => 'register']);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'role' => 'admin',
            'is_active' => true,
        ])) {
            return back()->withInput()->with('error', 'Invalid admin credentials.');
        }

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function register(Request $request)
    {
        if (!$this->registrationEnabled()) {
            return redirect()->route('admin.login.form')
                ->with('error', 'Admin registration is disabled.');
        }

        $payload = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date|before:today',
        ]);

        $admin = DB::transaction(function () use ($payload) {
            $admin = User::create([
                'name' => $payload['name'],
                'email' => $payload['email'],
                'password' => Hash::make($payload['password']),
                'phone' => $payload['phone'] ?? null,
                'date_of_birth' => $payload['date_of_birth'] ?? null,
                'role' => 'admin',
                'is_active' => true,
            ]);

            $admin->wallets()->create([
                'name' => 'Admin Main Wallet',
                'type' => 'main',
                'balance' => 0,
                'is_frozen' => false,
            ]);

            $admin->walletLimits()->createMany([
                [
                    'limit_type' => 'daily',
                    'max_amount' => 1000000,
                    'reset_date' => now()->toDateString(),
                ],
                [
                    'limit_type' => 'monthly',
                    'max_amount' => 10000000,
                    'reset_date' => now()->startOfMonth()->toDateString(),
                ],
                [
                    'limit_type' => 'per_transaction',
                    'max_amount' => 500000,
                ],
            ]);

            return $admin;
        });

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')
            ->with('success', 'Admin account created and logged in successfully.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login.form')
            ->with('success', 'Logged out successfully.');
    }

    private function registrationEnabled(): bool
    {
        return !User::where('role', 'admin')->exists();
    }
}

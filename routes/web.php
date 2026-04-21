<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminWebController;
use App\Http\Controllers\AdminWebAuthController;

Route::get('/', function () {
    return redirect()->route('admin.login.form');
});

Route::get('/login', function () {
    return redirect()->route('admin.login.form');
})->name('login');

Route::prefix('admin')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminWebAuthController::class, 'showLogin'])->name('admin.login.form');
        Route::post('/login', [AdminWebAuthController::class, 'login'])->name('admin.login');
        Route::get('/register', [AdminWebAuthController::class, 'showRegister'])->name('admin.register.form');
        Route::post('/register', [AdminWebAuthController::class, 'register'])->name('admin.register');
    });

    Route::middleware('admin.web')->group(function () {
        Route::get('/', fn() => redirect()->route('admin.dashboard'))->name('admin.home');
        Route::get('/dashboard', [AdminWebController::class, 'dashboard'])->name('admin.dashboard');
        Route::get('/users', [AdminWebController::class, 'users'])->name('admin.users');
        Route::get('/wallets', [AdminWebController::class, 'wallets'])->name('admin.wallets');
        Route::get('/wallet-transfer', [AdminWebController::class, 'walletTransfer'])->name('admin.wallet-transfer');
        Route::get('/commissions', [AdminWebController::class, 'commissions'])->name('admin.commissions');
        Route::get('/withdrawals', [AdminWebController::class, 'withdrawals'])->name('admin.withdrawals');
        Route::get('/withdraw-charges', [AdminWebController::class, 'withdrawCharges'])->name('admin.withdraw-charges');
        Route::get('/transactions', [AdminWebController::class, 'transactions'])->name('admin.transactions');
        Route::get('/transactions/export', [AdminWebController::class, 'exportTransactionsCsv'])->name('admin.transactions.export');
        Route::post('/transactions/{id}/recharge/approve', [AdminWebController::class, 'approveRecharge'])->name('admin.transactions.recharge.approve');
        Route::post('/transactions/{id}/recharge/reject', [AdminWebController::class, 'rejectRecharge'])->name('admin.transactions.recharge.reject');
        Route::get('/reports', [AdminWebController::class, 'reports'])->name('admin.reports');
        Route::get('/support', [AdminWebController::class, 'support'])->name('admin.support');
        Route::post('/support/{threadId}/reply', [AdminWebController::class, 'supportReply'])->name('admin.support.reply');
        Route::get('/api-management', [AdminWebController::class, 'apiManagement'])->name('admin.api-management');
        Route::get('/settings', [AdminWebController::class, 'settings'])->name('admin.settings');
        Route::get('/logs', [AdminWebController::class, 'logs'])->name('admin.logs');
        Route::get('/security', [AdminWebController::class, 'security'])->name('admin.security');
        Route::get('/profile', [AdminWebController::class, 'profile'])->name('admin.profile');
        Route::post('/notifications/{id}/read', [AdminWebController::class, 'markNotificationRead'])->name('admin.notifications.read');
        Route::post('/notifications/read-all', [AdminWebController::class, 'markAllNotificationsRead'])->name('admin.notifications.read-all');
        Route::get('/media/{path}', [AdminWebController::class, 'media'])->where('path', '.*')->name('admin.media');
        Route::post('/profile/photo', [AdminWebController::class, 'updateProfilePhoto'])->name('admin.profile.photo');
        Route::post('/users/create', [AdminWebController::class, 'createUser'])->name('admin.users.create');
        Route::get('/users/{id}/edit', [AdminWebController::class, 'editUser'])->name('admin.users.edit');
        Route::get('/users/{id}/profile', [AdminWebController::class, 'userProfile'])->name('admin.users.profile');
        Route::get('/users/{id}/history', [AdminWebController::class, 'userHistory'])->name('admin.users.history');
        Route::post('/users/{id}/toggle', [AdminWebController::class, 'toggleUser'])->name('admin.users.toggle');
        Route::post('/users/{id}/update', [AdminWebController::class, 'updateUser'])->name('admin.users.update');
        Route::post('/users/{id}/reset-password', [AdminWebController::class, 'resetUserPassword'])->name('admin.users.reset-password');
        Route::post('/users/{id}/delete', [AdminWebController::class, 'deleteUser'])->name('admin.users.delete');
        Route::post('/users/{id}/deduct-balance', [AdminWebController::class, 'deductUserBalance'])->name('admin.users.deduct-balance');
        Route::post('/wallets/{id}/toggle-freeze', [AdminWebController::class, 'toggleWallet'])->name('admin.wallets.toggle');
        Route::post('/wallets/adjust', [AdminWebController::class, 'adjustWallet'])->name('admin.wallets.adjust');
        Route::post('/wallets/force-settlement', [AdminWebController::class, 'forceSettlement'])->name('admin.wallets.force-settlement');
        Route::post('/wallets/transfer', [AdminWebController::class, 'transferBetweenWallets'])->name('admin.wallets.transfer');
        Route::post('/commissions/default', [AdminWebController::class, 'updateDefaultCommission'])->name('admin.commissions.default');
        Route::post('/commissions/override', [AdminWebController::class, 'setCommissionOverride'])->name('admin.commissions.override');
        Route::post('/commissions/override/{id}/delete', [AdminWebController::class, 'deleteCommissionOverride'])->name('admin.commissions.override.delete');
        Route::post('/withdrawals/settings', [AdminWebController::class, 'updateWithdrawSettings'])->name('admin.withdrawals.settings');
        Route::post('/withdrawals/{id}/approve', [AdminWebController::class, 'approveWithdraw'])->name('admin.withdrawals.approve');
        Route::post('/withdrawals/{id}/reject', [AdminWebController::class, 'rejectWithdraw'])->name('admin.withdrawals.reject');
        Route::post('/security/settings', [AdminWebController::class, 'updateSecuritySettings'])->name('admin.security.settings');
        Route::post('/settings', [AdminWebController::class, 'updateSystemSettings'])->name('admin.settings.update');
        Route::post('/settings/ertitech/test', [AdminWebController::class, 'testErtitechConnection'])->name('admin.settings.ertitech.test');
        Route::post('/logout', [AdminWebAuthController::class, 'logout'])->name('admin.logout');
    });
});

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\DepositController;
use App\Http\Controllers\Api\WithdrawController;
use App\Http\Controllers\Api\ScheduledTransferController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\DistributorController;
use App\Http\Controllers\Api\MasterDistributorController;
use App\Http\Controllers\Api\SuperDistributorController;
use App\Http\Controllers\Api\RetailerController;
use App\Http\Controllers\Api\SupportChatController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RetailerRechargeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/frontend-status', [AuthController::class, 'frontendStatus']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/public/distributors', [AuthController::class, 'distributors']);
Route::post('/deposit/webhook', [DepositController::class, 'webhook']);
Route::post('/deposit/payu/response', [DepositController::class, 'handlePayUResponse'])->name('api.deposit.payu.callback');
Route::post('/recharge/push-status', [RetailerRechargeController::class, 'pushStatus'])->name('api.recharge.push-status');

Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // Notifications (all roles)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read/{id}', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAll']);
    Route::get('/reports', [ReportController::class, 'index']);
    // Support tickets (super distributor & admin shared)
    Route::post('/support/ticket', [SupportTicketController::class, 'store']);
    Route::get('/support/tickets', [SupportTicketController::class, 'index']);
    Route::get('/support/messages/{thread}', [SupportTicketController::class, 'messages']);
    Route::post('/support/message', [SupportTicketController::class, 'sendMessage']);
    Route::post('/support/status', [SupportTicketController::class, 'updateStatus']);

    // Wallet routes
    Route::get('/wallets', [WalletController::class, 'index']);
    Route::post('/wallets', [WalletController::class, 'store']);
    Route::get('/wallets/limits', [WalletController::class, 'limits']);
    Route::put('/wallets/limits', [WalletController::class, 'updateLimits']);
    Route::post('/wallets/check-limits', [WalletController::class, 'checkLimits']);
    Route::get('/wallets/{id}', [WalletController::class, 'show']);
    Route::post('/wallets/{id}/freeze', [WalletController::class, 'freeze']);
    Route::get('/wallets/{id}/balance', [WalletController::class, 'balance']);

    // Transaction routes
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::get('/transactions/export', [TransactionController::class, 'export']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);

    // Transfer routes
    Route::post('/transfer', [TransferController::class, 'transfer']);

    // Deposit routes
    Route::post('/deposit', [DepositController::class, 'deposit']);
    Route::post('/deposit/create-order', [DepositController::class, 'createOrder']);
    Route::post('/deposit/verify', [DepositController::class, 'verifyPayment']);

    // Withdraw routes
    Route::post('/withdraw', [WithdrawController::class, 'withdraw']);
    Route::post('/withdraw/request-otp', [WithdrawController::class, 'requestOtp']);
    Route::get('/withdraw/history', [WithdrawController::class, 'withdrawalHistory']);
    Route::post('/withdraw/calculate-commission', [WithdrawController::class, 'calculateCommission']);

    // Scheduled Transfer routes
    Route::get('/scheduled-transfers', [ScheduledTransferController::class, 'index']);
    Route::post('/scheduled-transfers', [ScheduledTransferController::class, 'store']);
    Route::get('/scheduled-transfers/{id}', [ScheduledTransferController::class, 'show']);
    Route::put('/scheduled-transfers/{id}', [ScheduledTransferController::class, 'update']);
    Route::delete('/scheduled-transfers/{id}', [ScheduledTransferController::class, 'destroy']);
    Route::post('/scheduled-transfers/{id}/toggle', [ScheduledTransferController::class, 'toggle']);
    Route::post('/scheduled-transfers/execute', [ScheduledTransferController::class, 'executeScheduledTransfers']);

    // Admin routes (require admin role)
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/wallets', [AdminController::class, 'wallets']);
        Route::post('/transfer', [AdminController::class, 'transfer']);
        Route::get('/transactions', [AdminController::class, 'transactions']);
        Route::post('/wallets/{id}/freeze', [AdminController::class, 'freezeWallet']);
        Route::post('/users/{id}/toggle', [AdminController::class, 'toggleUser']);
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/users/{userId}/transactions', [AdminController::class, 'userTransactions']);
        Route::get('/users/{userId}/wallets', [AdminController::class, 'userWallets']);
        // Retailer chat for admin
        Route::get('/support/threads', [SupportChatController::class, 'adminIndex']);
        Route::get('/support/threads/{thread}', [SupportChatController::class, 'show']);
        Route::post('/support/threads/{thread}/reply', [SupportChatController::class, 'reply']);
    });

    // Distributor routes
    Route::middleware('role:distributor')->prefix('distributor')->group(function () {
        Route::get('/dashboard', [DistributorController::class, 'dashboard']);
        Route::get('/performance', [DistributorController::class, 'performance']);
        Route::get('/retailers', [DistributorController::class, 'retailers']);
        Route::post('/retailers', [DistributorController::class, 'createRetailer']);
        Route::put('/retailers/{id}', [DistributorController::class, 'updateRetailer']);
        Route::post('/retailers/{id}/toggle', [DistributorController::class, 'toggleRetailer']);
        Route::get('/retailers/{id}/transactions', [DistributorController::class, 'retailerTransactions']);
        Route::post('/retailers/{id}/transfer', [DistributorController::class, 'transferToRetailer']);
        Route::get('/transactions', [DistributorController::class, 'transactions']);
        Route::get('/withdraw-requests', [DistributorController::class, 'withdrawRequests']);
        Route::post('/withdraw-requests/{id}/approve', [DistributorController::class, 'approveWithdrawRequest']);
        Route::post('/withdraw-requests/{id}/reject', [DistributorController::class, 'rejectWithdrawRequest']);
    });

    // Master distributor routes
    Route::middleware('role:master_distributor')->prefix('master-distributor')->group(function () {
        Route::get('/dashboard', [MasterDistributorController::class, 'dashboard']);
        Route::get('/distributors', [MasterDistributorController::class, 'distributors']);
        Route::post('/distributors', [MasterDistributorController::class, 'createDistributor']);
        Route::put('/distributors/{id}', [MasterDistributorController::class, 'updateDistributor']);
        Route::post('/distributors/{id}/toggle', [MasterDistributorController::class, 'toggleDistributor']);
        Route::get('/distributors/{id}/transactions', [MasterDistributorController::class, 'distributorTransactions']);
        Route::post('/distributors/{id}/transfer', [MasterDistributorController::class, 'transferToDistributor']);
        Route::get('/transactions', [MasterDistributorController::class, 'transactions']);
        Route::get('/support/threads', [SupportChatController::class, 'retailerIndex']);
        Route::post('/support/threads', [SupportChatController::class, 'store']);
        Route::get('/support/threads/{thread}', [SupportChatController::class, 'show']);
        Route::post('/support/threads/{thread}/reply', [SupportChatController::class, 'reply']);
    });

    // Super distributor routes
    Route::middleware('role:super_distributor')->prefix('super-distributor')->group(function () {
        Route::get('/dashboard', [SuperDistributorController::class, 'dashboard']);
        Route::get('/distributors', [SuperDistributorController::class, 'distributors']);
        Route::post('/distributors', [SuperDistributorController::class, 'createDistributor']);
        Route::put('/distributors/{id}', [SuperDistributorController::class, 'updateDistributor']);
        Route::post('/distributors/{id}/toggle', [SuperDistributorController::class, 'toggleDistributor']);
        Route::get('/distributors/{id}/transactions', [SuperDistributorController::class, 'distributorTransactions']);
        Route::post('/distributors/{id}/transfer', [SuperDistributorController::class, 'transferToDistributor']);
        Route::get('/transactions', [SuperDistributorController::class, 'transactions']);
        Route::get('/support/threads', [SupportChatController::class, 'retailerIndex']);
        Route::post('/support/threads', [SupportChatController::class, 'store']);
        Route::get('/support/threads/{thread}', [SupportChatController::class, 'show']);
        Route::post('/support/threads/{thread}/reply', [SupportChatController::class, 'reply']);
        Route::get('/reports', [ReportController::class, 'index']);
    });

    // Retailer routes
    Route::middleware('role:retailer,user')->prefix('retailer')->group(function () {
        Route::get('/dashboard', [RetailerController::class, 'dashboard']);
        Route::get('/withdraw-requests', [RetailerController::class, 'withdrawRequests']);
        Route::get('/notifications', [RetailerController::class, 'notifications']);
        Route::post('/notifications/{id}/read', [RetailerController::class, 'markNotificationRead']);
        Route::post('/profile', [RetailerController::class, 'updateProfile']);
        Route::post('/change-password', [RetailerController::class, 'changePassword']);
        Route::post('/bank-details', [RetailerController::class, 'updateBankDetails']);
        Route::post('/kyc/upload', [RetailerController::class, 'uploadKyc']);
        Route::post('/ekyc/submit', [RetailerController::class, 'submitEkyc']);
        Route::get('/statement/export', [RetailerController::class, 'statementExport']);
        Route::post('/recharge/pay', [RetailerRechargeController::class, 'pay']);
        Route::post('/recharge/{id}/sync-status', [RetailerRechargeController::class, 'syncStatus']);
        // Retailer support chat
        Route::get('/support/threads', [SupportChatController::class, 'retailerIndex']);
        Route::post('/support/threads', [SupportChatController::class, 'store']);
        Route::get('/support/threads/{thread}', [SupportChatController::class, 'show']);
        Route::post('/support/threads/{thread}/reply', [SupportChatController::class, 'reply']);
    });
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

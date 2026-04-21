<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route the request
switch ($method) {
    case 'POST':
        if (strpos($path, '/deposit/create-order') !== false) {
            createDepositOrder();
        } elseif (strpos($path, '/deposit/verify') !== false) {
            verifyDeposit();
        } elseif (strpos($path, '/deposit') !== false) {
            processDeposit();
        }
        break;
    case 'GET':
        if (strpos($path, '/deposit') !== false) {
            getDepositHistory();
        }
        break;
}

function createDepositOrder() {
    try {
        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = Validator::make($input, [
            'amount' => 'required|numeric|min:100|max:100000',
            'wallet_id' => 'required|integer|exists:wallets,id'
        ]);

        if ($validator->fails()) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ]);
            return;
        }

        // Get authenticated user (simplified for demo)
        $user = User::find(1); // Test user - replace with actual auth
        
        // Get wallet
        $wallet = Wallet::where('id', $input['wallet_id'])
                    ->where('user_id', $user->id)
                    ->first();

        if (!$wallet) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Wallet not found'
            ]);
            return;
        }

        // Create Cashfree order (simplified for demo)
        $orderId = 'order_' . time() . '_' . rand(1000, 9999);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'order' => [
                    'id' => $orderId,
                    'amount' => $input['amount'] * 100, // Convert to paise
                    'currency' => 'INR',
                    'receipt' => 'receipt_' . time()
                ],
                'cashfree_app_id' => env('CASHFREE_APP_ID', 'cashfree_test_app_id'),
                'test_mode' => true
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create order: ' . $e->getMessage()
        ]);
    }
}

function verifyDeposit() {
    try {
        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = Validator::make($input, [
            'cashfree_order_id' => 'required|string',
            'cashfree_payment_id' => 'required|string',
            'wallet_id' => 'required|integer|exists:wallets,id',
            'amount' => 'required|numeric|min:100'
        ]);

        if ($validator->fails()) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ]);
            return;
        }

        // Get authenticated user (simplified for demo)
        $user = User::find(1); // Test user
        
        // Get wallet
        $wallet = Wallet::where('id', $input['wallet_id'])
                    ->where('user_id', $user->id)
                    ->first();

        if (!$wallet) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Wallet not found'
            ]);
            return;
        }

        // Create transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'to_wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => $input['amount'],
            'reference' => 'TXN' . strtoupper(uniqid()),
            'description' => 'Deposit via Cashfree',
            'status' => 'completed',
            'metadata' => json_encode([
                'cashfree_order_id' => $input['cashfree_order_id'],
                'cashfree_payment_id' => $input['cashfree_payment_id'],
                'test_mode' => true
            ])
        ]);

        // Update wallet balance
        $wallet->balance += $input['amount'];
        $wallet->save();

        echo json_encode([
            'success' => true,
            'message' => 'Deposit verified successfully',
            'data' => [
                'transaction_id' => $transaction->id,
                'new_balance' => $wallet->balance,
                'amount' => $input['amount']
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to verify deposit: ' . $e->getMessage()
        ]);
    }
}

function processDeposit() {
    try {
        // Get input data
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate input
        $validator = Validator::make($input, [
            'wallet_id' => 'required|integer|exists:wallets,id',
            'amount' => 'required|numeric|min:100|max:100000',
            'payment_method' => 'required|string|in:cashfree,bank_transfer,upi,credit_card,debit_card'
        ]);

        if ($validator->fails()) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ]);
            return;
        }

        // Get authenticated user (simplified for demo)
        $user = User::find(1); // Test user
        
        // Get wallet
        $wallet = Wallet::where('id', $input['wallet_id'])
                    ->where('user_id', $user->id)
                    ->first();

        if (!$wallet) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Wallet not found'
            ]);
            return;
        }

        // For demo purposes, process deposit immediately
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'to_wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => $input['amount'],
            'reference' => 'TXN' . strtoupper(uniqid()),
            'description' => "Deposit via {$input['payment_method']}",
            'status' => 'completed',
            'metadata' => json_encode([
                'payment_method' => $input['payment_method'],
                'test_mode' => true,
                'processed_at' => now()
            ])
        ]);

        // Update wallet balance
        $wallet->balance += $input['amount'];
        $wallet->save();

        echo json_encode([
            'success' => true,
            'message' => 'Deposit processed successfully',
            'data' => [
                'transaction_id' => $transaction->id,
                'new_balance' => $wallet->balance,
                'amount' => $input['amount'],
                'wallet_name' => $wallet->name
            ]
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to process deposit: ' . $e->getMessage()
        ]);
    }
}

function getDepositHistory() {
    try {
        // Get authenticated user (simplified for demo)
        $user = User::find(1); // Test user
        
        // Get deposit transactions
        $transactions = Transaction::where('user_id', $user->id)
                               ->where('type', 'deposit')
                               ->with(['toWallet'])
                               ->orderBy('created_at', 'desc')
                               ->get();

        echo json_encode([
            'success' => true,
            'data' => $transactions
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get deposit history: ' . $e->getMessage()
        ]);
    }
}

?>


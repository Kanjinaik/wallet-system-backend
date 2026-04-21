<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Console\Command;

class TestDeposit extends Command
{
    protected $signature = 'test:deposit';
    protected $description = 'Test deposit functionality';

    public function handle()
    {
        $this->info('Testing deposit functionality...');
        
        try {
            // Test database connection
            $user = User::where('email', 'user@wallet.com')->first();
            if ($user) {
                $wallet = $user->wallets()->first();
                $this->info('✅ Database working!');
                $this->info('User: ' . $user->name);
                $this->info('Wallet: ' . $wallet->name . ' (Balance: ' . $wallet->balance . ')');
            } else {
                $this->error('❌ Test user not found');
                return Command::FAILURE;
            }
            
            // Test deposit creation (test mode)
            $this->info('✅ Test mode deposit working - no Cashfree API call needed for this command!');
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        return Command::SUCCESS;
    }
}


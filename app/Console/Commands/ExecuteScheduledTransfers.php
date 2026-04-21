<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\ScheduledTransferController;
use Illuminate\Console\Command;

class ExecuteScheduledTransfers extends Command
{
    protected $signature = 'scheduled:execute';
    protected $description = 'Execute scheduled transfers that are due';

    public function handle(ScheduledTransferController $controller)
    {
        $this->info('Executing scheduled transfers...');
        
        $response = $controller->executeScheduledTransfers();
        $data = json_decode($response->getContent(), true);
        
        $this->info("Processed: {$data['total']} transfers");
        $this->info("Executed: {$data['executed']}");
        $this->info("Failed: {$data['failed']}");
        
        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use Carbon\Carbon;

class CleanupPendingTransactions extends Command
{
    protected $signature = 'swapiles:cleanup-pending';

    protected $description = 'Annule les transactions pending abandonnées';

    public function handle(): void
    {
        $transactions = Transaction::where('status', 'pending')
            ->where('created_at', '<', Carbon::now()->subHours(2))
            ->get();

        $count = 0;

        foreach ($transactions as $transaction) {

            $transaction->update([
                'status' => 'cancelled',
                'wallet_status' => 'cancelled',
            ]);

            $count++;
        }

        $this->info("{$count} transactions pending annulées.");
    }
}

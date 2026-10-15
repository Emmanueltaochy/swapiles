<?php

namespace App\Console\Commands;

use App\Support\LiveNotifications;
use Illuminate\Console\Command;

class SendLiveReminders extends Command
{
    protected $signature = 'live:rappels';

    protected $description = 'Prévient les membres inscrits qu’un live programmé commence bientôt';

    public function handle(): int
    {
        $this->info(LiveNotifications::envoyerRappels() . ' rappel(s) envoyé(s).');

        return self::SUCCESS;
    }
}

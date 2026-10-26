<?php

namespace App\Console\Commands;

use App\Support\GardeRelais;
use Illuminate\Console\Command;

/** Délai de garde au point relais : colis non retirés à temps => retour au vendeur. */
class ExpirerColisRelais extends Command
{
    protected $signature = 'relais:garde';

    protected $description = 'Signale les colis non retirés au point relais après le délai de garde';

    public function handle(): int
    {
        $this->info('Colis non retirés : ' . GardeRelais::expirer());

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Support\NotificationsVente;
use Illuminate\Console\Command;

/**
 * Relances des ventes en cours : « pense à expédier », « tu as reçu ton
 * colis ? », « ton colis t'attend au point relais »… Deux au plus par vente
 * et par sujet ; la nuit, elles attendent le matin (PushPolicy).
 */
class SendSaleReminders extends Command
{
    protected $signature = 'ventes:relances';

    protected $description = 'Relance vendeurs et acheteurs dont la vente attend une action';

    public function handle(): int
    {
        $this->info('Relances envoyées : ' . NotificationsVente::envoyerRelances());

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Support\Portefeuille;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Entretien quotidien du parrainage :
 *  - RGPD : efface les ouvertures d'invitation (IP chiffrée) de plus de 7 jours ;
 *  - fait expirer les crédits arrivés à échéance ;
 *  - prévient 7 jours avant l'expiration d'un crédit.
 */
class ParrainageEntretien extends Command
{
    protected $signature = 'parrainage:entretien';

    protected $description = 'Purge des invitations (RGPD), expiration des crédits et rappels J-7';

    public function handle(): int
    {
        $purgees = DB::table('invitations_ouvertes')
            ->where('created_at', '<', now()->subDays((int) config('referral.conservation_invitations_jours', 7)))
            ->delete();
        $expires = Portefeuille::expirer();
        $rappels = Portefeuille::envoyerRappelsExpiration();

        $this->info("{$purgees} invitation(s) effacée(s), {$expires} crédit(s) expiré(s), {$rappels} rappel(s).");

        return self::SUCCESS;
    }
}

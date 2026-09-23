<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use Illuminate\Console\Command;

/**
 * Ménage des jetons d'appareil devenus inutiles.
 *
 * Un jeton n'identifie pas un appareil de façon stable : il change à la
 * réinstallation de l'app, à l'effacement des données ou lors d'un
 * renouvellement par le service. Sans ménage, les lignes s'accumulent et le
 * nombre d'appareils affiché n'a plus aucun rapport avec le nombre réel
 * d'installations.
 *
 * L'app renvoie son jeton à CHAQUE lancement : un jeton plus revu depuis des
 * mois correspond donc à une installation disparue.
 */
class CleanupDeviceTokens extends Command
{
    protected $signature = 'push:cleanup-tokens
        {--days= : Ancienneté au-delà de laquelle un jeton est supprimé}
        {--dry-run : Affiche ce qui serait supprimé sans rien modifier}';

    protected $description = 'Supprime les jetons d’appareil plus revus depuis longtemps (réinstallations, désinstallations).';

    public function handle(): int
    {
        $jours = (int) ($this->option('days') ?: config('push.retention_days', 90));
        $dryRun = (bool) $this->option('dry-run');

        $query = DeviceToken::query()->obsoletes($jours);
        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Aucun jeton à supprimer.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("{$total} jeton(s) seraient supprimés (plus revus depuis {$jours} jours).");

            return self::SUCCESS;
        }

        $supprimes = $query->delete();

        $this->info("{$supprimes} jeton(s) supprimés (plus revus depuis {$jours} jours).");

        return self::SUCCESS;
    }
}

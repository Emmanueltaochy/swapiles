<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Valide d'office l'adresse e-mail des membres inscrits AVANT la correction de
 * l'envoi du lien de confirmation.
 *
 * Ces membres n'ont jamais reçu (ou jamais trouvé) leur lien : les laisser
 * « non vérifiés » les pénaliserait pour un problème qui ne vient pas d'eux.
 *
 * La date de coupure est figée dans le code : la commande peut donc tourner à
 * chaque déploiement sans jamais toucher aux inscriptions postérieures, qui
 * doivent, elles, confirmer normalement.
 */
class VerifyLegacyEmails extends Command
{
    protected $signature = 'users:verify-legacy-emails
        {--dry-run : Affiche les comptes concernés sans rien modifier}';

    protected $description = "Valide d'office l'e-mail des membres inscrits avant la correction de l'envoi du lien de confirmation.";

    /** Inscriptions antérieures à cette date : validées d'office. */
    public const CUTOFF = '2026-09-07 00:00:00';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = User::query()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', self::CUTOFF)
            // Comptes supprimés/anonymisés (RGPD) : on n'y touche pas.
            ->where('email', 'not like', '%@swapiles.invalid');

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Aucun compte à valider.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("{$total} compte(s) seraient validés d'office.");

            return self::SUCCESS;
        }

        $now = now();
        $updated = 0;

        // chunkById sur les identifiants pour ne pas charger tout le monde en mémoire.
        (clone $query)->select('id')->chunkById(500, function ($users) use (&$updated, $now) {
            $ids = $users->pluck('id')->all();
            $updated += User::whereIn('id', $ids)->update(['email_verified_at' => $now]);
        });

        $this->info("{$updated} compte(s) validés d'office (inscrits avant " . self::CUTOFF . ').');

        return self::SUCCESS;
    }
}

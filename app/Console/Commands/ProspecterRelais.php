<?php

namespace App\Console\Commands;

use App\Models\RelaisProspect;
use App\Support\ProspectionRelais;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Repère les commerces des îles (OpenStreetMap) et cherche leur e-mail.
 * Avec --si-demande : ne tourne que si l'admin a cliqué « Rechercher
 * maintenant » (le planificateur vérifie chaque minute, sans file d'attente).
 */
class ProspecterRelais extends Command
{
    public const DEMANDE = 'relais.prospection.demande';

    protected $signature = 'relais:prospects {ile? : une île, sinon toutes} {--emails=150 : sites web à visiter au plus} {--si-demande}';

    protected $description = 'Prospection points relais : repère les commerces et cherche leur e-mail';

    public function handle(): int
    {
        $ile = $this->argument('ile');
        if ($this->option('si-demande')) {
            $demande = Cache::pull(self::DEMANDE);
            // Toute première fois (juste après la mise en ligne) : on lance sans attendre la nuit.
            if (! $demande && ! Cache::has('relais.prospection.derniere') && RelaisProspect::count() === 0) {
                $demande = 'toutes';
            }
            if (! $demande) {
                return self::SUCCESS;
            }
            $ile = $demande === 'toutes' ? null : $demande;
        }

        foreach ($ile ? [$ile] : array_keys(ProspectionRelais::ILES) as $nom) {
            try {
                $r = ProspectionRelais::importer($nom);
                $this->info("{$nom} : {$r['trouves']} commerces, {$r['nouveaux']} nouveaux");
            } catch (Throwable $e) {
                report($e);
                $this->warn("{$nom} : " . $e->getMessage());
            }
        }

        $emails = ProspectionRelais::chercherEmails((int) $this->option('emails'));
        $this->info("E-mails trouvés sur les sites : {$emails}");
        Cache::put('relais.prospection.derniere', now()->toIso8601String(), now()->addYear());

        return self::SUCCESS;
    }
}

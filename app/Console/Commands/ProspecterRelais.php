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

    protected $signature = 'relais:prospects {ile? : une île, sinon toutes} {--emails=150 : sites web à visiter au plus (0 : aucun)}
        {--si-demande : seulement si l’admin l’a demandé} {--si-vide : seulement si aucun commerce n’est encore enregistré} {--sans-import : seulement chercher les e-mails}';

    public const BILAN = 'relais.prospection.bilan';

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

        if ($this->option('si-vide') && RelaisProspect::count() > 0) {
            $this->info('Des commerces sont déjà enregistrés : rien à faire.');

            return self::SUCCESS;
        }

        $bilan = [];
        if (! $this->option('sans-import')) {
            Cache::put(self::BILAN, '⏳ recherche en cours depuis ' . now()->format('H:i') . '…', now()->addDay());
            foreach ($ile ? [$ile] : array_keys(ProspectionRelais::ILES) as $nom) {
                try {
                    $r = ProspectionRelais::importer($nom);
                    $bilan[] = "{$nom} : {$r['trouves']} commerces ({$r['nouveaux']} nouveaux)";
                } catch (Throwable $e) {
                    report($e);
                    $bilan[] = "{$nom} : ⚠️ " . $e->getMessage();
                }
                $this->info(end($bilan));
            }
        }

        if ((int) $this->option('emails') > 0) {
            $emails = ProspectionRelais::chercherEmails((int) $this->option('emails'));
            $bilan[] = "{$emails} e-mail(s) trouvé(s) sur les sites";
            $this->info(end($bilan));
        }

        // Montré en haut de la page « Prospection relais » de l'admin.
        Cache::put(self::BILAN, now()->format('d/m H:i') . ' · ' . implode(' · ', $bilan), now()->addYear());
        Cache::put('relais.prospection.derniere', now()->toIso8601String(), now()->addYear());

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\CandidatureRelais;
use App\Support\CandidaturesRelaisBoite;
use Illuminate\Console\Command;
use Throwable;

/** Récupère les demandes de commerçants « Devenir point relais » reçues sur contact@ (anonyme dans les journaux). */
class ImporterCandidaturesRelais extends Command
{
    protected $signature = 'relais:candidatures-importer {--jours=120}';

    protected $description = 'Commerces candidats point relais : récupère les demandes reçues par e-mail sur contact@';

    public function handle(): int
    {
        try {
            $r = CandidaturesRelaisBoite::importer((int) $this->option('jours'));
            $this->line("E-mails de demande trouvés : {$r['lus']} · nouvelles candidatures ajoutées : {$r['ajoutees']}");
        } catch (Throwable $e) {
            $this->warn('Boîte contact@ illisible (' . class_basename($e) . ').');
            report($e);
        }
        $this->line('Commerces candidats : ' . CandidatureRelais::count() . ' au total, ' . CandidatureRelais::where('statut', 'nouvelle')->count() . ' à rappeler');

        return self::SUCCESS;
    }
}

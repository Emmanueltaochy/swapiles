<?php

namespace App\Console\Commands;

use App\Models\RelaisProspect;
use App\Support\ProspectionRelais;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Envoi quotidien des mails de prospection, dans la limite du jour, en
 * commençant par les villes où il y a le plus d'annonces et de demandes de
 * relais. Se met en pause depuis l'admin (bouton « Pause »).
 */
class EnvoyerProspectionRelais extends Command
{
    public const PAUSE = 'relais.prospection.pause';

    protected $signature = 'relais:prospection-envoyer {--max= : envois au plus (sinon le plafond du jour)} {--ile=} {--force : même en pause (bouton de l’admin)}';

    protected $description = 'Prospection points relais : envoie les mails de présentation du jour';

    public function handle(): int
    {
        if (Cache::get(self::PAUSE) && ! $this->option('force')) {
            $this->info('Envoi automatique en pause.');

            return self::SUCCESS;
        }

        $place = ProspectionRelais::plafondJour() - ProspectionRelais::envoyesAujourdhui();
        $max = min($place, $this->option('max') !== null ? (int) $this->option('max') : $place);
        if ($max <= 0) {
            $this->info('Plafond du jour atteint.');

            return self::SUCCESS;
        }

        ProspectionRelais::recalculerDemande();
        $prospects = RelaisProspect::query()->contactable()
            ->when($this->option('ile'), fn ($q, $ile) => $q->where('territoire', $ile))
            ->orderByDesc('demande')->orderBy('id')
            ->limit($max)->get();

        $envoyes = 0;
        foreach ($prospects as $p) {
            try {
                $envoyes += ProspectionRelais::envoyer($p) ? 1 : 0;
            } catch (Throwable $e) {
                report($e);
            }
        }
        $this->info("Mails envoyés : {$envoyes}");

        return self::SUCCESS;
    }
}

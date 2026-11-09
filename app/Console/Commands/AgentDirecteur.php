<?php

namespace App\Console\Commands;

use App\Console\Commands\EnvoyerProspectionRelais;
use App\Models\AgentRapport;
use App\Models\AgentReponse;
use App\Models\ProspectRse;
use App\Models\RelaisProspect;
use App\Support\Agent\BoiteReponses;
use App\Support\Agent\Ia;
use App\Support\Agent\Prospection;
use App\Support\Agent\ProspectionRse;
use App\Support\Agent\RapportQuotidien;
use App\Support\ProspectionRelais;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Agent directeur de Swap'Îles. Une seule commande, plusieurs missions :
 *   agent:directeur rapport      résumé du matin + 3 actions (7 h)
 *   agent:directeur prospecter   mails de la tranche horaire (8 h – 16 h)
 *   agent:directeur reponses     réponses des prospects sur contact@
 *   agent:directeur annuaire     organismes publics de chaque île (annuaire officiel)
 *   agent:directeur etat         état anonyme pour les journaux de déploiement
 */
class AgentDirecteur extends Command
{
    protected $signature = 'agent:directeur {mission : rapport|prospecter|reponses|annuaire|etat} {--sans-envoi : rapport sans e-mail ni notification} {--si-vide : annuaire seulement si aucun organisme enregistré}';

    protected $description = 'Agent directeur : résumé quotidien, prospection, réponses, annuaire';

    public function handle(): int
    {
        $mission = (string) $this->argument('mission');
        if ($mission !== 'etat' && ! config('agent.actif')) {
            $this->info('Agent désactivé (AGENT_ACTIF).');

            return self::SUCCESS;
        }

        return match ($mission) {
            'rapport' => $this->rapport(),
            'prospecter' => $this->prospecter(),
            'reponses' => $this->reponses(),
            'annuaire' => $this->annuaire(),
            'etat' => $this->etat(),
            default => $this->erreur($mission),
        };
    }

    private function erreur(string $mission): int
    {
        $this->error("Mission inconnue : {$mission}");

        return self::FAILURE;
    }

    private function rapport(): int
    {
        $r = RapportQuotidien::generer(! $this->option('sans-envoi'));
        $this->info('Résumé du ' . $r->jour->format('d/m/Y') . ' : ' . count($r->actions ?? []) . ' action(s) proposée(s).');

        return self::SUCCESS;
    }

    private function prospecter(): int
    {
        if (Prospection::enPause()) {
            $this->info('Prospection en pause.');

            return self::SUCCESS;
        }
        $part = Prospection::partDeCetteHeure();
        if ($part <= 0) {
            $this->info('Rien à envoyer à cette heure.');

            return self::SUCCESS;
        }

        $envoyes = ['relais' => 0, 'rse' => 0, 'relances' => 0];
        $envoyer = function (callable $f, string $cle) use (&$envoyes) {
            try {
                if ($f()) {
                    $envoyes[$cle]++;
                    if (! app()->runningUnitTests()) {
                        sleep(3); // espacer les envois : les filtres anti-spam préfèrent
                    }

                    return true;
                }
            } catch (Throwable $e) {
                report($e);
            }

            return false;
        };

        $rse = Prospection::rseValidee();
        if ($rse) {
            foreach (ProspectionRse::aRelancer()->limit(max(1, intdiv($part, 3)))->get() as $p) {
                $envoyer(fn () => ProspectionRse::relancer($p), 'relances');
            }
        }
        $reste = $part - array_sum($envoyes);
        $partRse = $rse ? (int) ceil($reste / 2) : 0;
        foreach ($rse ? ProspectionRse::prochains($partRse) : [] as $p) {
            $envoyer(fn () => ProspectionRse::envoyer($p), 'rse');
        }
        $reste = $part - array_sum($envoyes);
        if ($reste > 0 && ! Cache::get(EnvoyerProspectionRelais::PAUSE)) {
            ProspectionRelais::recalculerDemande();
            foreach (RelaisProspect::query()->contactable()->orderByDesc('demande')->orderBy('id')->limit($reste)->get() as $p) {
                $envoyer(fn () => ProspectionRelais::envoyer($p), 'relais');
            }
        }
        // Peu de commerces à contacter : on complète avec les organismes publics.
        $reste = $part - array_sum($envoyes);
        if ($rse && $reste > 0) {
            foreach (ProspectionRse::prochains($reste) as $p) {
                $envoyer(fn () => ProspectionRse::envoyer($p), 'rse');
            }
        }

        $this->info("Envoyés : {$envoyes['relais']} commerces, {$envoyes['rse']} organismes, {$envoyes['relances']} relances.");

        return self::SUCCESS;
    }

    private function reponses(): int
    {
        if (! BoiteReponses::configuree()) {
            $this->info('Boîte contact@ non configurée pour la lecture.');

            return self::SUCCESS;
        }
        try {
            $r = BoiteReponses::relever();
            $this->info("Messages examinés : {$r['lus']} · réponses de prospects : {$r['reponses']}");
        } catch (Throwable $e) {
            // Le message peut contenir l'adresse du serveur : on ne garde que le type.
            $this->warn('Lecture de la boîte impossible (' . class_basename($e) . ').');
            report($e);
        }

        return self::SUCCESS;
    }

    private function annuaire(): int
    {
        if ($this->option('si-vide') && ProspectRse::exists()) {
            $this->line('Organismes publics déjà enregistrés : rien à faire.');

            return self::SUCCESS;
        }
        foreach (array_keys(ProspectionRse::DEPARTEMENTS) as $ile) {
            try {
                $r = ProspectionRse::importer($ile);
                $this->line("{$ile} : {$r['trouves']} organismes, {$r['nouveaux']} nouveaux, {$r['avec_email']} avec e-mail");
            } catch (Throwable $e) {
                $this->warn("{$ile} : annuaire indisponible (" . class_basename($e) . ')');
            }
        }

        return self::SUCCESS;
    }

    /** État anonyme (journaux de déploiement publics) : seulement des totaux. */
    private function etat(): int
    {
        $oui = fn ($b) => $b ? 'oui' : 'non';
        $this->line('Agent actif : ' . $oui(config('agent.actif') && ! Prospection::enPause())
            . ' | boîte no-reply branchée : ' . $oui(Prospection::boiteSeparee())
            . ' | lecture contact@ : ' . $oui(BoiteReponses::configuree())
            . ' | IA : ' . $oui(Ia::disponible()) . ' (' . number_format(Ia::depenseDuMois(), 2, ',', '') . ' $ ce mois)');
        $this->line('Prospection : ' . Prospection::envoyesAujourdhui() . '/' . Prospection::plafondJour() . ' mails aujourd’hui'
            . ' | modèle collectivités validé : ' . $oui(Prospection::rseValidee()));
        $this->line('Commerces : ' . RelaisProspect::whereNotNull('contacte_at')->count() . ' contactés, ' . RelaisProspect::query()->contactable()->count() . ' à contacter'
            . ' | Organismes publics : ' . ProspectRse::count() . ' repérés, ' . ProspectRse::whereNotNull('email')->count() . ' avec e-mail, '
            . ProspectRse::whereNotNull('contacte_at')->count() . ' contactés');
        $rep = AgentReponse::selectRaw('categorie, COUNT(*) n')->groupBy('categorie')->pluck('n', 'categorie')->all();
        $this->line('Réponses reçues : ' . ($rep ? collect($rep)->map(fn ($n, $c) => "{$c} {$n}")->implode(', ') : 'aucune pour l’instant'));
        $dernier = AgentRapport::orderByDesc('jour')->first();
        $this->line('Dernier résumé : ' . ($dernier ? $dernier->jour->format('d/m/Y') : 'aucun'));

        return self::SUCCESS;
    }
}

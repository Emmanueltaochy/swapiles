<?php

namespace App\Support\Agent;

use Anthropic\Client;
use App\Support\IaDepot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Appels à Claude pour l'agent directeur : réponse JSON structurée, coût
 * noté (fonction « agent ») et budget mensuel propre, séparé de l'IA dépôt.
 */
class Ia
{
    public static function depenseDuMois(): float
    {
        return (float) DB::table('ia_depot_usages')->where('fonction', 'agent')->where('created_at', '>=', now()->startOfMonth())->sum('cout_usd');
    }

    public static function disponible(): bool
    {
        return filled(config('ia_depot.cle')) || app()->bound('agent.client')
            ? self::depenseDuMois() < (float) config('agent.budget_mensuel_usd', 15)
            : false;
    }

    /**
     * @return array{donnees:?array,cout:float}
     */
    /**
     * La réflexion du modèle compte dans $maxJetons : on laisse large et on
     * règle la profondeur avec $effort (low pour l'oral et le tri, medium pour le résumé).
     */
    public static function json(string $modele, string $consignes, string $texte, array $schema, int $maxJetons = 8000, string $effort = 'low'): array
    {
        if (! self::disponible()) {
            return ['donnees' => null, 'cout' => 0.0];
        }
        // Un caractère mal encodé (e-mail reçu, recherche d'un membre) suffit à faire refuser l'appel.
        $consignes = mb_scrub($consignes, 'UTF-8');
        $texte = mb_scrub($texte, 'UTF-8');
        $client = app()->bound('agent.client')
            ? app('agent.client')
            : new Client(apiKey: (string) config('ia_depot.cle'), requestOptions: ['timeout' => 90, 'maxRetries' => 1]);

        try {
            $reponse = $client->messages->create(
                maxTokens: $maxJetons,
                model: $modele,
                system: [['type' => 'text', 'text' => $consignes]],
                messages: [['role' => 'user', 'content' => [['type' => 'text', 'text' => $texte]]]],
                outputConfig: ['effort' => $effort, 'format' => ['type' => 'json_schema', 'schema' => $schema]],
            );
        } catch (Throwable $e) {
            IaDepot::noter(null, $modele, 0, 0, 0, 'erreur', 'agent');
            Log::warning('Agent directeur : appel IA en échec', ['type' => get_class($e)]);
            // Raison donnée par l'API (technique, sans donnée personnelle), pour le diagnostic.
            $raison = preg_replace('/[\w.+-]+@[\w-]+\.[\w.]+/', '[e-mail]', (string) $e->getMessage());
            self::suivre($modele, 'echec', class_basename($e) . ' : ' . mb_substr((string) $raison, 0, 160), 0);

            return ['donnees' => null, 'cout' => 0.0];
        }

        $entree = (int) $reponse->usage->inputTokens + (int) ($reponse->usage->cacheCreationInputTokens ?? 0) + (int) ($reponse->usage->cacheReadInputTokens ?? 0);
        $sortie = (int) $reponse->usage->outputTokens;
        $json = null;
        foreach ($reponse->content as $bloc) {
            if (($bloc->type ?? null) === 'text') {
                $json = json_decode($bloc->text, true);
                break;
            }
        }
        $ok = is_array($json) && ($reponse->stopReason ?? null) !== 'refusal';
        IaDepot::noter(null, $modele, 0, $entree, $sortie, $ok ? 'ok' : 'erreur', 'agent');
        self::suivre($modele, $ok ? 'ok' : 'illisible', (string) ($reponse->stopReason ?? '?'), $sortie);
        [$pe, $ps] = config('ia_depot.tarifs.' . $modele, [0, 0]);

        return ['donnees' => $ok ? $json : null, 'cout' => round(($entree * $pe + $sortie * $ps) / 1_000_000, 6)];
    }

    /** Suivi anonyme des derniers appels (journaux de déploiement) : résultat, raison d'arrêt, jetons. */
    private static function suivre(string $modele, string $resultat, string $arret, int $sortie): void
    {
        \App\Support\CarnetDiagnostic::ajouter('agent-ia', [
            'quand' => now()->format('Y-m-d H:i'), 'modele' => $modele, 'resultat' => $resultat, 'arret' => mb_substr($arret, 0, 200), 'sortie' => $sortie,
        ], 30);
    }
}

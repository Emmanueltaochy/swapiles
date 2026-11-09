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
    public static function json(string $modele, string $consignes, string $texte, array $schema, int $maxJetons = 3000): array
    {
        if (! self::disponible()) {
            return ['donnees' => null, 'cout' => 0.0];
        }
        $client = app()->bound('agent.client')
            ? app('agent.client')
            : new Client(apiKey: (string) config('ia_depot.cle'), requestOptions: ['timeout' => 90, 'maxRetries' => 1]);

        try {
            $reponse = $client->messages->create(
                maxTokens: $maxJetons,
                model: $modele,
                system: [['type' => 'text', 'text' => $consignes]],
                messages: [['role' => 'user', 'content' => [['type' => 'text', 'text' => $texte]]]],
                outputConfig: ['format' => ['type' => 'json_schema', 'schema' => $schema]],
            );
        } catch (Throwable $e) {
            IaDepot::noter(null, $modele, 0, 0, 0, 'erreur', 'agent');
            Log::warning('Agent directeur : appel IA en échec', ['type' => get_class($e)]);

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
        [$pe, $ps] = config('ia_depot.tarifs.' . $modele, [0, 0]);

        return ['donnees' => $ok ? $json : null, 'cout' => round(($entree * $pe + $sortie * $ps) / 1_000_000, 6)];
    }
}

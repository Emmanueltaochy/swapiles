<?php

namespace App\Support\Agent;

use App\Models\AgentRapport;
use App\Models\AgentReponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * « Parler au CEO » : Emmanuel pose une question (à la voix ou au clavier),
 * l'agent répond à l'oral, en quelques phrases, à partir des vrais chiffres
 * de Swap'Îles (totaux), du résumé du jour et des réponses des prospects.
 */
class Conversation
{
    public const LIMITE_JOUR = 80;

    /**
     * @param  array<int,array{q:string,r:string}>  $historique  derniers échanges (pour les questions de suite)
     */
    public static function repondre(string $question, array $historique = []): string
    {
        $question = trim(Str::limit(strip_tags($question), 500, ''));
        if ($question === '') {
            return 'Je n’ai rien entendu. Tu peux répéter ?';
        }
        if (RateLimiter::tooManyAttempts('agent:voix:' . today()->toDateString(), self::LIMITE_JOUR)) {
            return 'On a beaucoup parlé aujourd’hui : je fais une pause jusqu’à demain pour garder le budget sous contrôle.';
        }
        RateLimiter::hit('agent:voix:' . today()->toDateString(), 86400);

        if (! Ia::disponible()) {
            return 'Mon intelligence n’est pas disponible pour le moment : soit la clé manque, soit le budget du mois est atteint. Tout le reste continue de tourner.';
        }

        $echanges = '';
        foreach (array_slice($historique, -6) as $e) {
            $echanges .= 'Emmanuel : ' . Str::limit((string) ($e['q'] ?? ''), 300) . "\nToi : " . Str::limit((string) ($e['r'] ?? ''), 600) . "\n";
        }

        $res = Ia::json((string) config('agent.modele_voix', config('agent.modele_rapport')), self::consignes(),
            "Date et heure : " . now()->locale('fr')->translatedFormat('l j F Y, H\hi') . " (La Réunion)\n\n"
            . "Données de Swap'Îles (JSON) :\n" . json_encode(self::contexte(), JSON_UNESCAPED_UNICODE) . "\n\n"
            . ($echanges !== '' ? "Échanges précédents :\n{$echanges}\n" : '')
            . "Question d'Emmanuel : {$question}",
            ['type' => 'object', 'properties' => ['reponse' => ['type' => 'string']], 'required' => ['reponse'], 'additionalProperties' => false],
            8000, 'low');

        $reponse = is_string($res['donnees']['reponse'] ?? null) ? trim($res['donnees']['reponse']) : '';

        return $reponse !== '' ? Str::limit($reponse, 1200) : 'Je n’ai pas réussi à réfléchir cette fois-ci. Tu peux reposer la question ?';
    }

    /** Ce que le CEO sait (totaux, résumé du jour, dernières réponses de prospects). */
    public static function contexte(): array
    {
        return Cache::remember('agent:voix:contexte', 300, function () {
            $rapport = AgentRapport::orderByDesc('jour')->first();

            return [
                'chiffres' => RapportQuotidien::chiffres(),
                'resume_du_jour' => $rapport ? ['jour' => $rapport->jour->toDateString(), 'texte' => $rapport->texte, 'actions' => $rapport->actions] : null,
                'dernieres_reponses_prospects' => AgentReponse::orderByDesc('recu_at')->limit(10)->get()->map(function (AgentReponse $r) {
                    $p = $r->prospect();

                    return ['quand' => $r->recu_at?->format('d/m H:i'), 'qui' => $p?->nom, 'type' => $r->prospect_type === 'rse' ? 'collectivité' : 'commerce',
                        'ile' => $p?->territoire, 'categorie' => $r->categorie, 'resume' => $r->resume, 'traitee' => (bool) $r->vu_at];
                })->all(),
            ];
        });
    }

    private static function consignes(): string
    {
        return <<<'TXT'
Tu es le directeur général (agent IA) de Swap'Îles, l'application de seconde main des Outre-mer née à La Réunion : achat, vente, dons, échanges, Colissimo, points relais chez des commerçants, lives de vente, comptes Pro. Tu parles À L'ORAL avec Emmanuel, le fondateur : ta réponse sera lue à voix haute par son téléphone.
Règles :
- Français parlé, chaleureux et direct, tutoiement, comme un associé de confiance. Une touche réunionnaise discrète est permise, sans en faire trop.
- 1 à 4 phrases courtes. Pas de liste, pas de titre, pas d'emoji, pas de markdown, pas d'abréviation (écris « euros », « pour cent »).
- Uniquement les chiffres présents dans les données. Si l'information n'y est pas, dis-le simplement et dis où la trouver (admin, Bureau du CEO).
- Quand c'est utile, termine par une recommandation concrète pour aujourd'hui.
- Jamais de prêt ni de grosse dépense, jamais de concurrent cité, jamais d'action sur l'argent des membres ; tu proposes, Emmanuel décide.
TXT;
    }
}

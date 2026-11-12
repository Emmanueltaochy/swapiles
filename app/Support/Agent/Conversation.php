<?php

namespace App\Support\Agent;

use App\Models\AgentRapport;
use App\Models\AgentReponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * « Parler au CEO » : Emmanuel pose une question (à la voix ou au clavier),
 * l'agent répond à l'oral à partir des vrais chiffres. Il peut aussi
 * préparer l'envoi d'une réponse à un prospect (et la modifier à la voix) ;
 * l'e-mail ne part qu'après une confirmation explicite (« oui, envoie »).
 */
class Conversation
{
    public const LIMITE_JOUR = 80;

    /** Mots qui valent confirmation d'un envoi (vérifiés côté serveur, pas seulement par l'IA). */
    public const CONFIRMATION = '/\b(oui|ouais|envoie|envoies|envoyer|envoyez|vas[- ]y|go|confirme|confirmé|d\'accord|ok|okay|c\'est bon|valide)\b/iu';

    /**
     * @param  array<int,array{q:string,r:string}>  $historique  derniers échanges
     * @param  int|null  $enAttente  réponse de prospect prête à partir, en attente de confirmation
     * @return array{texte:string,enAttente:?int,apercu:?array{nom:string,a:string,texte:string}}
     */
    public static function repondre(string $question, array $historique = [], ?int $enAttente = null): array
    {
        $question = trim(Str::limit(strip_tags($question), 500, ''));
        $sortie = fn (string $t, ?int $attente = null) => ['texte' => $t, 'enAttente' => $attente, 'apercu' => $attente ? self::apercu($attente) : null];
        if ($question === '') {
            return $sortie('Je n’ai rien entendu. Tu peux répéter ?', $enAttente);
        }
        if (RateLimiter::tooManyAttempts('agent:voix:' . today()->toDateString(), self::LIMITE_JOUR)) {
            return $sortie('On a beaucoup parlé aujourd’hui : je fais une pause jusqu’à demain pour garder le budget sous contrôle.');
        }
        RateLimiter::hit('agent:voix:' . today()->toDateString(), 86400);

        if (! Ia::disponible()) {
            return $sortie('Mon intelligence n’est pas disponible pour le moment : soit la clé manque, soit le budget du mois est atteint. Tout le reste continue de tourner.');
        }

        $echanges = '';
        foreach (array_slice($historique, -6) as $e) {
            $echanges .= 'Emmanuel : ' . Str::limit((string) ($e['q'] ?? ''), 300) . "\nToi : " . Str::limit((string) ($e['r'] ?? ''), 600) . "\n";
        }
        $attente = $enAttente ? self::apercu($enAttente) : null;

        $res = Ia::json((string) config('agent.modele_voix', config('agent.modele_rapport')), self::consignes(),
            'Date et heure : ' . now()->locale('fr')->translatedFormat('l j F Y, H\hi') . " (La Réunion)\n\n"
            . "Données de Swap'Îles (JSON) :\n" . json_encode(self::contexte(), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n\n"
            . ($attente ? "ENVOI EN ATTENTE DE CONFIRMATION : réponse n°{$enAttente} à {$attente['nom']} ({$attente['a']}) :\n{$attente['texte']}\n\n" : "Aucun envoi en attente.\n\n")
            . ($echanges !== '' ? "Échanges précédents :\n{$echanges}\n" : '')
            . "Ce que dit Emmanuel : {$question}",
            [
                'type' => 'object',
                'properties' => [
                    'reponse' => ['type' => 'string'],
                    'action' => ['type' => 'string', 'enum' => ['aucune', 'preparer_envoi', 'modifier_brouillon', 'confirmer_envoi', 'annuler_envoi']],
                    'reponse_id' => ['type' => 'integer'],
                    'nouveau_brouillon' => ['type' => 'string'],
                ],
                'required' => ['reponse', 'action', 'reponse_id', 'nouveau_brouillon'],
                'additionalProperties' => false,
            ],
            8000, 'low');

        $d = is_array($res['donnees']) ? $res['donnees'] : [];
        $texte = trim((string) ($d['reponse'] ?? ''));
        if ($texte === '') {
            return $sortie('Je n’ai pas réussi à réfléchir cette fois-ci. Tu peux reposer la question ?', $enAttente);
        }
        $texte = Str::limit($texte, 1200);

        return match ($d['action'] ?? 'aucune') {
            'preparer_envoi' => self::preparer((int) ($d['reponse_id'] ?? 0), null, $texte, $sortie),
            'modifier_brouillon' => self::preparer((int) ($d['reponse_id'] ?? 0) ?: (int) $enAttente, (string) ($d['nouveau_brouillon'] ?? ''), $texte, $sortie),
            'confirmer_envoi' => self::confirmer($question, $enAttente, $sortie),
            'annuler_envoi' => $sortie('D’accord, je n’envoie rien.'),
            default => $sortie($texte, $enAttente),
        };
    }

    /** Met une réponse en attente de confirmation (éventuellement réécrite). */
    private static function preparer(int $id, ?string $nouveauBrouillon, string $texte, callable $sortie): array
    {
        $r = AgentReponse::find($id);
        if ($nouveauBrouillon !== null && $r && trim($nouveauBrouillon) !== '' && ! $r->envoye_at) {
            $r->forceFill(['brouillon' => Str::limit(trim($nouveauBrouillon), 4000)])->save();
        }
        if ($raison = EnvoiReponse::empechement($r)) {
            return $sortie('Je ne peux pas préparer cet envoi : ' . $raison . '.');
        }
        $nom = $r->prospect()?->nom ?? $r->expediteur;
        if (! preg_match('/confirm|oui, envoie|j.envoie\s*\?/iu', $texte)) {
            $texte .= ' Je l’envoie à ' . $nom . ' depuis contact@ ? Dis « oui, envoie » pour confirmer.';
        }

        return $sortie($texte, $r->id);
    }

    /** Envoie la réponse en attente, seulement si Emmanuel a clairement dit oui. */
    private static function confirmer(string $question, ?int $enAttente, callable $sortie): array
    {
        if (! $enAttente) {
            return $sortie('Il n’y a aucun e-mail prêt à partir. Dis-moi d’abord à qui répondre.');
        }
        if (! preg_match(self::CONFIRMATION, $question)) {
            return $sortie('Je préfère être sûr : dis « oui, envoie » pour que je l’envoie.', $enAttente);
        }
        $r = AgentReponse::find($enAttente);
        try {
            EnvoiReponse::envoyer($r);
        } catch (Throwable $e) {
            report($e);

            return $sortie('L’envoi n’a pas marché : ' . ($e instanceof \RuntimeException && ! str_contains($e->getMessage(), '@') ? $e->getMessage() : 'le serveur d’e-mail a refusé') . '. Tu peux réessayer avec le bouton dans le Bureau du CEO.');
        }

        return $sortie('C’est envoyé à ' . ($r->prospect()?->nom ?? $r->expediteur) . ' depuis contact@, avec ta signature. Tu le retrouveras dans les messages envoyés.');
    }

    /** @return array{nom:string,a:string,texte:string}|null */
    public static function apercu(int $id): ?array
    {
        $r = AgentReponse::find($id);

        return $r ? ['nom' => (string) ($r->prospect()?->nom ?? $r->expediteur), 'a' => (string) $r->expediteur, 'texte' => EnvoiReponse::texte($r)] : null;
    }

    /** Ce que le CEO sait (totaux, résumé du jour, réponses de prospects avec leur numéro). */
    public static function contexte(): array
    {
        $base = Cache::remember('agent:voix:contexte', 300, function () {
            $rapport = AgentRapport::orderByDesc('jour')->first();

            return [
                'chiffres' => RapportQuotidien::chiffres(),
                'resume_du_jour' => $rapport ? ['jour' => $rapport->jour->toDateString(), 'texte' => $rapport->texte, 'actions' => $rapport->actions] : null,
            ];
        });
        // Toujours à jour (pas de cache) : les envois changent l'état.
        $base['reponses_prospects'] = AgentReponse::orderByDesc('recu_at')->limit(12)->get()->map(function (AgentReponse $r) {
            $p = $r->prospect();

            return ['numero' => $r->id, 'quand' => $r->recu_at?->format('d/m H:i'), 'qui' => $p?->nom, 'type' => $r->prospect_type === 'rse' ? 'collectivité' : 'commerce',
                'ile' => $p?->territoire, 'telephone' => $p?->telephone, 'categorie' => $r->categorie, 'ce_quil_a_ecrit' => Str::limit((string) $r->extrait, 600),
                'resume' => $r->resume, 'reponse_preparee' => $r->brouillon ? EnvoiReponse::texte($r) : null,
                'deja_envoyee' => $r->envoye_at?->format('d/m H:i'), 'traitee' => (bool) $r->vu_at];
        })->all();

        return $base;
    }

    private static function consignes(): string
    {
        return <<<'TXT'
Tu es le directeur général (agent IA) de Swap'Îles, l'application de seconde main des Outre-mer née à La Réunion : achat, vente, dons, échanges, Colissimo, points relais chez des commerçants, lives de vente, comptes Pro. Tu parles À L'ORAL avec Emmanuel, le fondateur : ta réponse (« reponse ») sera lue à voix haute par son téléphone.
Règles de parole :
- Parle comme au téléphone avec ton associé : naturel, chaleureux, direct, tutoiement. Des petits mots d'oral bienvenus (« alors », « bon », « franchement », « bonne nouvelle »), sans en abuser. Une touche réunionnaise discrète est permise.
- Va droit au but : 1 à 4 phrases courtes, l'essentiel d'abord, pas de formule creuse ni de répétition de la question.
- Arrondis les chiffres comme à l'oral (« un peu plus de mille sept cents membres ») sauf quand le chiffre exact compte.
- Pas de liste, pas de titre, pas d'emoji, pas de markdown, pas d'abréviation (écris « euros », « pour cent »).
- Uniquement les chiffres et faits présents dans les données. Sinon dis-le et dis où regarder (Bureau du CEO).
- Quand c'est utile, termine par une recommandation concrète pour aujourd'hui.
Actions (champ « action », sinon « aucune ») — tu proposes, Emmanuel décide :
- preparer_envoi : Emmanuel veut répondre à un prospect (« réponds à… », « envoie le mail que tu as préparé à… »). Mets son « numero » dans « reponse_id ». Dans « reponse », résume en une ou deux phrases ce que dit la réponse préparée et demande s'il confirme. Si plusieurs prospects sont possibles et qu'il n'a pas dit lequel, action « aucune » et demande lequel.
- modifier_brouillon : il veut changer le texte (« ajoute que… », « dis-lui plutôt… »). Mets le texte complet réécrit dans « nouveau_brouillon » (vouvoiement, terminé par « Bien cordialement, » sans signature), le numéro dans « reponse_id », et redemande confirmation.
- confirmer_envoi : UNIQUEMENT s'il y a un envoi en attente ET qu'Emmanuel dit clairement oui (« oui », « envoie », « vas-y », « c'est bon »).
- annuler_envoi : il dit non ou d'annuler.
Ne dis jamais qu'un e-mail est parti : c'est le système qui le confirme. N'invente aucun prix ni promesse dans un brouillon. Jamais de prêt, de grosse dépense, ni de concurrent cité.
Quand il n'y a pas d'action, mets « reponse_id » à 0 et « nouveau_brouillon » à "".
TXT;
    }
}

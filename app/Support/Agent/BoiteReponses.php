<?php

namespace App\Support\Agent;

use App\Models\AgentReponse;
use App\Models\Notification;
use App\Models\ProspectRse;
use App\Models\RelaisProspect;
use App\Models\Reglage;
use App\Models\User;
use App\Support\AdminEvent;
use Illuminate\Support\Str;
use Throwable;

/**
 * Réponses des prospects dans la boîte contact@ : l'agent ne lit QUE les
 * mails venant d'un commerce ou d'un organisme déjà contacté (adresse ou
 * domaine identiques) ; les messages des membres ne sont jamais ouverts.
 * Tri par l'IA, désinscription appliquée tout de suite, alerte si intérêt.
 */
class BoiteReponses
{
    public const DERNIER = 'agent.imap.dernier';

    public static function configuree(): bool
    {
        return filled(config('agent.imap.hote')) && filled(config('agent.imap.utilisateur')) && filled(config('agent.imap.mot_de_passe'));
    }

    /** État de la lecture, sans aucun secret : ce qui manque, ou le résultat d'une connexion d'essai. */
    public static function etat(bool $essayer = true): string
    {
        $manque = array_keys(array_filter([
            'hôte' => blank(config('agent.imap.hote')),
            'identifiant' => blank(config('agent.imap.utilisateur')),
            'mot de passe' => blank(config('agent.imap.mot_de_passe')),
        ]));
        if ($manque) {
            return 'non (manque : ' . implode(', ', $manque) . ')';
        }
        $hote = (string) config('agent.imap.hote');
        if (! $essayer) {
            return 'oui (' . $hote . ')';
        }
        $imap = new Imap();
        try {
            $imap->connecter($hote, (int) config('agent.imap.port'), (string) config('agent.imap.utilisateur'), (string) config('agent.imap.mot_de_passe'));
            $imap->choisir('INBOX');

            return 'oui, connexion réussie (' . $hote . ')';
        } catch (Throwable $e) {
            return 'connexion refusée (' . $hote . ', ' . (str_contains($e->getMessage(), 'LOGIN') ? 'identifiant ou mot de passe' : 'serveur injoignable') . ')';
        } finally {
            $imap->deconnecter();
        }
    }

    /** @return array{lus:int,reponses:int} */
    public static function relever(?Imap $imap = null): array
    {
        if (! self::configuree()) {
            return ['lus' => 0, 'reponses' => 0];
        }
        $imap ??= new Imap();
        $imap->connecter((string) config('agent.imap.hote'), (int) config('agent.imap.port'), (string) config('agent.imap.utilisateur'), (string) config('agent.imap.mot_de_passe'));
        try {
            $validite = $imap->choisir('INBOX');
            $etat = (array) Reglage::lire(self::DERNIER, []);
            $dernier = ($etat['validite'] ?? null) === $validite ? (int) ($etat['uid'] ?? 0) : 0;
            $uids = array_values(array_filter($imap->depuis(now()->subDays(21)), fn ($u) => $u > $dernier));
            sort($uids);
            $lus = $reponses = 0;
            foreach (array_slice($uids, 0, 300) as $uid) {
                $lus++;
                $entetes = Courriel::entetes($imap->entetes($uid));
                $prospect = self::prospectDe(Courriel::adresse($entetes['from'] ?? null));
                if ($prospect) {
                    $reponses += self::traiter($prospect, $entetes, fn () => $imap->message($uid)) ? 1 : 0;
                }
                Reglage::ecrire(self::DERNIER, ['validite' => $validite, 'uid' => $uid]);
            }

            return ['lus' => $lus, 'reponses' => $reponses];
        } finally {
            $imap->deconnecter();
        }
    }

    /** Le commerce ou l'organisme contacté qui a écrit (adresse exacte, sinon même domaine pro). */
    public static function prospectDe(?string $adresse): RelaisProspect|ProspectRse|null
    {
        if (! $adresse) {
            return null;
        }
        $nous = array_filter([strtolower((string) config('agent.expediteur.adresse')), strtolower((string) config('agent.expediteur.reponses')), 'contact@swapiles.com']);
        if (in_array($adresse, $nous, true) || str_ends_with($adresse, '@swapiles.com')) {
            return null;
        }
        $contactes = fn ($q) => $q->whereNotNull('contacte_at');
        $p = RelaisProspect::query()->where($contactes)->whereRaw('LOWER(email) = ?', [$adresse])->first()
            ?? ProspectRse::query()->where($contactes)->whereRaw('LOWER(email) = ?', [$adresse])->first();
        if ($p) {
            return $p;
        }
        $domaine = Str::after($adresse, '@');
        if (in_array($domaine, (array) config('agent.domaines_publics', []), true)) {
            return null;
        }

        return ProspectRse::query()->where($contactes)->whereRaw('LOWER(email) LIKE ?', ['%@' . $domaine])->orderByDesc('contacte_at')->first()
            ?? RelaisProspect::query()->where($contactes)->whereRaw('LOWER(email) LIKE ?', ['%@' . $domaine])->orderByDesc('contacte_at')->first();
    }

    /** Trie et enregistre une réponse. False si déjà vue. */
    public static function traiter(RelaisProspect|ProspectRse $p, array $entetes, callable $brut): bool
    {
        $type = $p instanceof ProspectRse ? 'rse' : 'relais';
        $messageId = Str::limit($entetes['message-id'] ?? ('sans-id-' . md5(($entetes['from'] ?? '') . ($entetes['date'] ?? '') . ($entetes['subject'] ?? ''))), 250, '');
        if (AgentReponse::where('message_id', $messageId)->exists()) {
            return false;
        }
        $auto = isset($entetes['auto-submitted']) && strtolower($entetes['auto-submitted']) !== 'no' || isset($entetes['x-autoreply']);
        $texte = Courriel::texte($brut());
        $tri = match (true) {
            $auto => ['categorie' => 'absence', 'resume' => 'Réponse automatique.', 'brouillon' => ''],
            mb_strlen(trim($texte)) < 3 => ['categorie' => 'autre', 'resume' => 'Message vide ou illisible : à ouvrir dans la boîte contact@.', 'brouillon' => ''],
            default => self::trier($p, $entetes['subject'] ?? '', $texte),
        };

        $r = AgentReponse::create([
            'message_id' => $messageId,
            'prospect_type' => $type,
            'prospect_id' => $p->id,
            'expediteur' => Str::limit((string) Courriel::adresse($entetes['from'] ?? ''), 190, ''),
            'sujet' => Str::limit($entetes['subject'] ?? '', 250),
            'recu_at' => rescue(fn () => \Carbon\Carbon::parse($entetes['date'] ?? 'now'), now(), false),
            'categorie' => $tri['categorie'],
            'resume' => Str::limit($tri['resume'], 1000),
            'brouillon' => $tri['brouillon'] !== '' ? Str::limit($tri['brouillon'], 4000) : null,
            'extrait' => Str::limit($texte, 1500),
        ]);
        self::appliquer($p, $r);

        return true;
    }

    /** @return array{categorie:string,resume:string,brouillon:string} */
    public static function trier(RelaisProspect|ProspectRse $p, string $sujet, string $texte): array
    {
        $qui = $p instanceof ProspectRse ? 'un organisme public (' . (config('agent.rse_types.' . $p->type) ?? $p->type) . ') contacté pour un partenariat réemploi / pouvoir d’achat'
            : 'un commerce contacté pour devenir point relais Swap’Îles (1 € par colis, sans engagement)';
        $res = Ia::json((string) config('agent.modele_tri'), self::consignesTri(), "Expéditeur : {$qui}, « {$p->nom} » ({$p->territoire}).\nSujet : {$sujet}\n\nMessage :\n{$texte}", [
            'type' => 'object',
            'properties' => [
                'categorie' => ['type' => 'string', 'enum' => array_keys(AgentReponse::CATEGORIES)],
                'resume' => ['type' => 'string'],
                'brouillon' => ['type' => 'string'],
            ],
            'required' => ['categorie', 'resume', 'brouillon'],
            'additionalProperties' => false,
        ], 8000, 'low')['donnees'];

        if (is_array($res) && isset(AgentReponse::CATEGORIES[$res['categorie'] ?? ''])) {
            return ['categorie' => $res['categorie'], 'resume' => (string) ($res['resume'] ?? ''), 'brouillon' => (string) ($res['brouillon'] ?? '')];
        }

        // Sans IA : quelques mots-clés sûrs.
        $t = mb_strtolower($sujet . ' ' . $texte);
        $categorie = match (true) {
            (bool) preg_match('/d[ée]sinscri|ne plus (me |nous )?(contacter|écrire|ecrire)|retirez|stop\b|unsubscribe/u', $t) => 'desinscription',
            (bool) preg_match('/absent|absence|cong[ée]s?\b|out of office|réponse automatique/u', $t) => 'absence',
            (bool) preg_match('/pas int[ée]ress|non merci|ne sommes pas/u', $t) => 'refus',
            default => 'autre',
        };

        return ['categorie' => $categorie, 'resume' => Str::limit($texte, 200), 'brouillon' => ''];
    }

    private static function consignesTri(): string
    {
        return <<<'TXT'
Tu es l'assistant d'Emmanuel Taochy, fondateur de Swap'Îles (application de seconde main des Outre-mer : achat, vente, dons, échanges ; points relais chez des commerçants ; partenariats réemploi avec les collectivités).
Tu reçois UNIQUEMENT la réponse écrite par le prospect (notre mail d'origine a été retiré). Classe-la avec prudence :
- interesse : la personne dit CLAIREMENT oui, veut en savoir plus, demande un rendez-vous, un appel ou des documents. En cas de doute, ce n'est PAS « interesse ».
- question : elle pose une question précise sans dire oui ni non.
- refus : pas intéressée.
- desinscription : demande de ne plus être contactée.
- absence : réponse automatique, absence, accusé de réception automatique, changement d'adresse.
- autre : tout le reste, y compris « bien reçu », « merci », « nous reviendrons vers vous », « transmis au service concerné », message vide ou incompréhensible.
« resume » : une phrase en français simple qui dit ce que la personne a ÉCRIT (cite ses mots clés entre guillemets), et ses coordonnées si elle en donne. N'invente rien.
« brouillon » : pour interesse et question uniquement, une réponse courte, chaleureuse et professionnelle, au nom d'Emmanuel, qui propose un échange de 15 minutes, terminée par « Bien cordialement, » SANS signature (elle est ajoutée automatiquement). N'invente aucun chiffre, aucun prix, aucune promesse. Ne cite aucun concurrent. Sinon chaîne vide.
TXT;
    }

    /** Statut du prospect et alerte. */
    private static function appliquer(RelaisProspect|ProspectRse $p, AgentReponse $r, bool $alerter = true): void
    {
        $maj = [];
        if ($r->categorie !== 'absence') {
            $maj['repondu_at'] = $p->repondu_at ?? now();
        }
        $maj['statut'] = match ($r->categorie) {
            'desinscription' => 'desinscrit',
            'refus' => 'refuse',
            'interesse' => 'interesse',
            default => $p instanceof ProspectRse
                ? ($r->categorie !== 'absence' && in_array($p->statut, ['contacte', 'relance', 'interesse'], true) ? 'repondu' : $p->statut)
                : ($p->statut === 'interesse' ? 'contacte' : $p->statut),
        };
        $p->forceFill($maj)->save();

        if ($alerter && in_array($r->categorie, ['interesse', 'question'], true)) {
            Alerte::envoyer(
                ($r->categorie === 'interesse' ? '🔥 Prospect intéressé : ' : '❓ Question d’un prospect : ') . $p->nom,
                self::fiche($p, $r),
                url('/admin/bureau-du-ceo'),
                'agent_reponse'
            );
        }
    }

    /** Tout ce qu'il faut pour rappeler : qui, comment le joindre, ce qu'il a écrit, la réponse proposée. */
    public static function fiche(RelaisProspect|ProspectRse $p, AgentReponse $r): string
    {
        $type = $p instanceof ProspectRse ? (config('agent.rse_types.' . $p->type) ?? 'Organisme public') : 'Commerce (point relais)';
        $lignes = [
            'Qui : ' . $p->nom . ' — ' . $type . ($p->ville ? ', ' . $p->ville : '') . ($p->territoire ? ' (' . $p->territoire . ')' : ''),
            'E-mail : ' . $r->expediteur,
        ];
        if ($p->telephone) {
            $lignes[] = 'Téléphone : ' . $p->telephone;
        }
        $lignes[] = 'Reçu le : ' . ($r->recu_at?->format('d/m/Y à H:i') ?? '?');
        $lignes[] = '';
        $lignes[] = 'En bref : ' . $r->resume;
        $lignes[] = '';
        $lignes[] = 'Son message :';
        $lignes[] = $r->extrait ?: '(vide)';
        if ($r->brouillon) {
            $lignes[] = '';
            $lignes[] = 'Réponse proposée (à envoyer depuis contact@) :';
            $lignes[] = $r->brouillon;
        }

        return implode("\n", $lignes);
    }

    /**
     * Refait le tri des réponses déjà reçues avec les règles actuelles (sans
     * alerte) : corrige les « intéressé » trop généreux.
     *
     * @return array<string,int> catégories après le tri
     */
    public static function retrier(): array
    {
        foreach (AgentReponse::whereNotIn('categorie', ['absence'])->get() as $r) {
            $p = $r->prospect();
            if (! $p) {
                continue;
            }
            $tri = mb_strlen(trim((string) $r->extrait)) < 3
                ? ['categorie' => 'autre', 'resume' => 'Message vide ou illisible : à ouvrir dans la boîte contact@.', 'brouillon' => '']
                : self::trier($p, (string) $r->sujet, (string) $r->extrait);
            $r->forceFill(['categorie' => $tri['categorie'], 'resume' => Str::limit($tri['resume'], 1000),
                'brouillon' => $tri['brouillon'] !== '' ? Str::limit($tri['brouillon'], 4000) : null])->save();
            self::appliquer($p, $r, false);
        }

        return AgentReponse::selectRaw('categorie, COUNT(*) n')->groupBy('categorie')->pluck('n', 'categorie')->map(fn ($n) => (int) $n)->all();
    }
}

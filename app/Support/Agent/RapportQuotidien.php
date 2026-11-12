<?php

namespace App\Support\Agent;

use App\Models\AgentRapport;
use App\Models\AgentReponse;
use App\Models\Notification;
use App\Models\ProspectRse;
use App\Models\RelaisProspect;
use App\Models\SearchNoResult;
use App\Models\User;
use App\Support\AdminEvent;
use App\Support\CarnetDiagnostic;
use App\Support\TableauDeBord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Résumé du matin de l'agent directeur : chiffres agrégés (aucune donnée
 * personnelle), analyse par l'IA et 3 actions à valider (Go / Non).
 */
class RapportQuotidien
{
    /** Chiffres du jour, uniquement des totaux. */
    public static function chiffres(): array
    {
        $aujourdhui = today();
        $hier = $aujourdhui->copy()->subDay();
        $garder = fn (array $e) => array_intersect_key($e, array_flip(['inscriptions', 'annonces', 'ventes', 'volume', 'revenus', 'ventes_declarees', 'messages']));

        $reponsesHier = AgentReponse::where('created_at', '>=', $hier)->where('created_at', '<', $aujourdhui)
            ->selectRaw('categorie, COUNT(*) n')->groupBy('categorie')->pluck('n', 'categorie')->map(fn ($n) => (int) $n)->all();

        $photos = array_filter(CarnetDiagnostic::lire('depot-photos'), fn ($c) => ($c['quand'] ?? '') >= $hier->format('Y-m-d H:i'));

        return [
            'date' => $aujourdhui->toDateString(),
            'hier' => $garder(TableauDeBord::entre($hier, $aujourdhui)),
            '7_derniers_jours' => $garder(TableauDeBord::entre(now()->subDays(7), null)),
            '7_jours_precedents' => $garder(TableauDeBord::entre(now()->subDays(14), now()->subDays(7))),
            'membres_total' => User::count(),
            'annonces_en_ligne_par_ile' => TableauDeBord::annoncesParIle(),
            'a_traiter' => TableauDeBord::aTraiter(),
            'recherches_sans_resultat_7j' => SearchNoResult::where('created_at', '>=', now()->subDays(7))
                ->selectRaw('term, COUNT(*) n')->groupBy('term')->orderByDesc('n')->limit(10)->pluck('n', 'term')->map(fn ($n) => (int) $n)->all(),
            'depot_photos_hier' => [
                'choix' => count($photos),
                'avec_photo_non_ajoutee' => count(array_filter($photos, fn ($c) => ($c['erreur'] ?? '') !== 'rien_recu' && ($c['ajoutees'] ?? 0) < ($c['choisies'] ?? 0))),
                'galerie_sans_photo_recue' => count(array_filter($photos, fn ($c) => ($c['erreur'] ?? '') === 'rien_recu')),
            ],
            'prospection' => [
                'boite_no_reply_branchee' => Prospection::boiteSeparee(),
                'plafond_du_jour' => Prospection::plafondJour(),
                'envoyes_hier' => RelaisProspect::whereBetween('contacte_at', [$hier, $aujourdhui])->count()
                    + ProspectRse::whereBetween('contacte_at', [$hier, $aujourdhui])->count() + ProspectRse::whereBetween('relance_at', [$hier, $aujourdhui])->count(),
                'reponses_hier' => $reponsesHier,
                'relais' => ['contactes' => RelaisProspect::whereNotNull('contacte_at')->count(), 'interesses' => RelaisProspect::where('statut', 'interesse')->count(),
                    'devenus_relais' => RelaisProspect::where('statut', 'relais')->count(), 'a_contacter' => RelaisProspect::query()->contactable()->count()],
                'collectivites' => ['contactees' => ProspectRse::whereNotNull('contacte_at')->count(), 'interessees' => ProspectRse::where('statut', 'interesse')->count(),
                    'a_contacter' => ProspectRse::query()->contactable()->count(), 'modele_valide' => Prospection::rseValidee()],
                'reponses_a_lire' => AgentReponse::whereNull('vu_at')->whereIn('categorie', ['interesse', 'question'])->count(),
            ],
            // Commerces et organismes (pas des particuliers) qui attendent un rappel d'Emmanuel.
            'prospects_a_rappeler' => self::aRappeler(),
            // Commerçants qui veulent devenir point relais (formulaire du site), pas encore rappelés.
            'commerces_candidats_point_relais' => \App\Models\CandidatureRelais::where('statut', 'nouvelle')->orderBy('created_at')->limit(10)->get()
                ->map(fn ($c) => ['commerce' => $c->commerce, 'ville' => $c->ville, 'depuis' => $c->created_at?->diffForHumans()])->all(),
            'decisions_recentes' => AgentRapport::where('jour', '>=', now()->subDays(7)->toDateString())->where('jour', '<', $aujourdhui->toDateString())
                ->orderBy('jour')->get()->flatMap(fn ($r) => collect($r->actions ?? [])->map(fn ($a) => ['jour' => $r->jour->toDateString(), 'action' => $a['titre'] ?? '', 'decision' => $a['decision'] ?? 'sans réponse']))->values()->all(),
        ];
    }

    /** @return array<int,array{qui:string,type:string,ile:?string,depuis:string,ce_quil_a_dit:?string}> */
    public static function aRappeler(): array
    {
        return AgentReponse::whereNull('vu_at')->whereIn('categorie', ['interesse', 'question'])->orderBy('recu_at')->limit(10)->get()
            ->map(function (AgentReponse $r) {
                $p = $r->prospect();

                return ['qui' => (string) ($p?->nom ?? '?'), 'type' => $r->prospect_type === 'rse' ? 'collectivité' : 'commerce',
                    'ile' => $p?->territoire, 'depuis' => $r->recu_at?->diffForHumans() ?? '?', 'ce_quil_a_dit' => $r->resume];
            })->all();
    }

    public static function generer(bool $envoyer = true): AgentRapport
    {
        $chiffres = self::chiffres();
        $res = Ia::json((string) config('agent.modele_rapport'), self::consignes(), "Chiffres du jour (JSON) :\n" . json_encode($chiffres, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE), [
            'type' => 'object',
            'properties' => [
                'resume' => ['type' => 'string'],
                'actions' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => [
                        'titre' => ['type' => 'string'],
                        'pourquoi' => ['type' => 'string'],
                        'comment' => ['type' => 'string'],
                        'domaine' => ['type' => 'string', 'enum' => ['produit', 'marketing', 'prospection', 'technique', 'communaute']],
                    ],
                    'required' => ['titre', 'pourquoi', 'comment', 'domaine'],
                    'additionalProperties' => false,
                ]],
            ],
            'required' => ['resume', 'actions'],
            'additionalProperties' => false,
        ], 16000, 'medium');

        $d = $res['donnees'];
        $actions = collect(is_array($d['actions'] ?? null) ? $d['actions'] : [])->take(3)->values()
            ->map(fn ($a, $i) => ['id' => $i + 1] + array_map(fn ($v) => Str::limit((string) $v, 600), (array) $a) + ['decision' => null])->all();
        $texte = is_string($d['resume'] ?? null) ? $d['resume'] : self::texteSansIa($chiffres);

        $rapport = AgentRapport::updateOrCreate(['jour' => $chiffres['date']], [
            'chiffres' => $chiffres,
            'texte' => $texte,
            'actions' => $actions,
            'cout_usd' => $res['cout'],
        ]);

        if ($envoyer) {
            self::envoyer($rapport);
        }

        return $rapport;
    }

    private static function texteSansIa(array $c): string
    {
        $h = $c['hier'];

        return "Hier : {$h['inscriptions']} inscriptions, {$h['annonces']} annonces, {$h['ventes']} ventes en ligne. "
            . "Prospection : {$c['prospection']['envoyes_hier']} mails envoyés. (Analyse IA indisponible aujourd'hui.)";
    }

    private static function consignes(): string
    {
        return <<<'TXT'
Tu es le directeur général (agent IA) de Swap'Îles, application de seconde main des Outre-mer (La Réunion d'abord, puis Guadeloupe, Martinique, Guyane, Mayotte) : achat, vente, dons, échanges, livraison Colissimo, points relais chez des commerçants, lives de vente, comptes Pro. Le fondateur, Emmanuel, n'est pas développeur, lit sur son téléphone, n'investit pas d'argent personnel et ne prend pas de crédit.
Objectif principal : plus de ventes conclues chaque semaine. Objectifs secondaires : plus de points relais, des partenariats réemploi avec les collectivités, la confiance des membres.
À partir des chiffres (totaux uniquement) :
1. « resume » : 4 à 7 phrases en français très simple, tutoiement. Ce qui monte, ce qui baisse (compare les 7 derniers jours aux 7 précédents), le point d'attention du jour, la prospection. Cite les vrais chiffres, n'en invente aucun.
2. « actions » : exactement 3 actions concrètes pour aujourd'hui, faisables sans budget ou presque, classées par impact. « comment » dit précisément quoi faire en 2-3 étapes. Tiens compte des décisions récentes (ne repropose pas une action refusée, fais avancer celles acceptées).
Si « prospects_a_rappeler » ou « commerces_candidats_point_relais » ne sont pas vides, commence le résumé par eux (qui, ce qu'ils ont dit) : c'est la priorité du jour.
Interdits : citer un concurrent, proposer un prêt ou une dépense importante, toucher à l'argent des membres, écrire aux membres sans validation.
TXT;
    }

    public static function envoyer(AgentRapport $r): void
    {
        $lignes = [];
        $rappels = self::aRappeler();
        if ($rappels) {
            $lignes[] = '🔥 À RAPPELER (' . count($rappels) . ') :';
            foreach ($rappels as $x) {
                $lignes[] = '- ' . $x['qui'] . ' (' . $x['type'] . ($x['ile'] ? ', ' . $x['ile'] : '') . ', ' . $x['depuis'] . ') : ' . $x['ce_quil_a_dit'];
            }
            $lignes[] = '';
        }
        $candidats = \App\Models\CandidatureRelais::where('statut', 'nouvelle')->orderBy('created_at')->get();
        if ($candidats->count()) {
            $lignes[] = '🏪 COMMERCES QUI VEULENT DEVENIR POINT RELAIS (' . $candidats->count() . ') :';
            foreach ($candidats as $c) {
                $lignes[] = '- ' . $c->commerce . ($c->ville ? ' (' . $c->ville . ')' : '') . ($c->telephone ? ' · ' . $c->telephone : '') . ' · reçue ' . $c->created_at?->diffForHumans();
            }
            $lignes[] = '';
        }
        $lignes[] = (string) $r->texte;
        $lignes[] = '';
        foreach ($r->actions ?? [] as $a) {
            $lignes[] = $a['id'] . '. ' . ($a['titre'] ?? '') . "\n   Pourquoi : " . ($a['pourquoi'] ?? '') . "\n   Comment : " . ($a['comment'] ?? '');
        }
        $lignes[] = "\nGo / Non pour chaque action dans le Bureau du CEO.";

        Alerte::envoyer('☀️ Résumé du jour Swap’Îles (' . $r->jour->format('d/m') . ')' . ($rappels ? ' · ' . count($rappels) . ' à rappeler' : ''),
            implode("\n", $lignes), url('/admin/bureau-du-ceo'), 'agent_rapport');
    }
}

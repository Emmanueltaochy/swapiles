<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Croissance mois par mois : les chiffres qu'un repreneur demande
 * (volume, revenus, liquidité, fidélité, audience), tous agrégés et
 * anonymes. Mêmes règles de calcul que le tableau de bord.
 */
class Croissance
{
    /** Une annonce « se vend » si elle part dans ce délai. */
    public const JOURS_LIQUIDITE = 60;

    /**
     * Paliers de la plateforme et objectifs à atteindre (sur 30 jours).
     * « Rachetable » : le palier « sérieuse » tenu une année entière.
     */
    public const PALIERS = [
        1 => ['nom' => 'Démarrage', 'emoji' => '🌱', 'objectifs' => []],
        2 => ['nom' => 'Ça marche', 'emoji' => '🚀', 'objectifs' => ['ventes' => 300, 'liquidite' => 20]],
        3 => ['nom' => 'Plateforme sérieuse', 'emoji' => '🏆', 'objectifs' => ['ventes' => 3000, 'visiteurs_jour' => 1000, 'revenus' => 5000]],
        4 => ['nom' => 'Rachetable', 'emoji' => '💎', 'objectifs' => ['mois_serieux' => 12]],
    ];

    /**
     * Une ligne par mois, du premier mois d'activité au mois en cours.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function mois(int $max = 24): array
    {
        $premier = User::query()->min('created_at');
        $debut = ($premier ? Carbon::parse($premier) : now())->startOfMonth();
        $limite = now()->startOfMonth()->subMonths($max - 1);
        if ($debut->lt($limite)) {
            $debut = $limite;
        }

        $lignes = [];
        $precedente = null;
        for ($m = $debut->copy(); $m->lte(now()); $m->addMonth()) {
            $ligne = self::unMois($m->copy());
            // Un mois en cours ne se compare pas à un mois complet.
            $comparable = $precedente && ! $ligne['en_cours'];
            $ligne['evolution_volume'] = $comparable ? self::evolution($ligne['volume'], $precedente['volume']) : null;
            $ligne['evolution_membres'] = $comparable ? self::evolution($ligne['inscriptions'], $precedente['inscriptions']) : null;
            $lignes[] = $ligne;
            $precedente = $ligne;
        }

        return $lignes;
    }

    /** @return array<string,mixed> */
    public static function unMois(Carbon $mois): array
    {
        $debut = $mois->copy()->startOfMonth();
        $fin = $debut->copy()->addMonth();
        $enCours = $fin->gt(now());

        $chiffres = TableauDeBord::entre($debut, $fin);
        $enLigne = Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS)->enLigne()
            ->where('created_at', '>=', $debut)->where('created_at', '<', $fin);
        $volume = (float) $chiffres['volume'] + (float) (clone $enLigne)->sum('credit_utilise');
        $ventes = (int) $chiffres['ventes'];

        // Acheteurs du mois qui avaient déjà acheté en ligne avant : ils reviennent.
        $acheteurs = (clone $enLigne)->distinct()->pluck('buyer_id');
        $revenants = $acheteurs->isEmpty() ? 0 : Transaction::query()
            ->whereIn('status', TableauDeBord::STATUTS_VENDUS)->enLigne()
            ->where('created_at', '<', $debut)
            ->whereIn('buyer_id', $acheteurs)
            ->distinct()->count('buyer_id');

        $visiteurs = self::visiteurs($debut, $fin);

        return [
            'mois' => $debut,
            'libelle' => ucfirst($debut->locale('fr')->translatedFormat('F Y')),
            'en_cours' => $enCours,
            'visiteurs' => $visiteurs['uniques'],
            'visiteurs_jour' => $visiteurs['par_jour'],
            'inscriptions' => (int) $chiffres['inscriptions'],
            'annonces' => (int) $chiffres['annonces'],
            'ventes' => $ventes,
            'volume' => round($volume, 2),
            'revenus' => round((float) $chiffres['revenus'], 2),
            'panier_moyen' => $ventes > 0 ? round($volume / $ventes, 2) : 0.0,
            'ventes_declarees' => (int) $chiffres['ventes_declarees'],
            'acheteurs' => $acheteurs->count(),
            'acheteurs_revenants' => $revenants,
            'fidelite' => $acheteurs->count() > 0 ? round($revenants / $acheteurs->count() * 100, 1) : null,
            'liquidite' => self::liquidite($debut, $fin),
        ];
    }

    /**
     * Part des annonces créées entre deux dates vendues en moins de
     * JOURS_LIQUIDITE jours (en ligne ou main propre). Null tant que le
     * délai n'est pas écoulé pour toutes : le chiffre serait sous-estimé.
     */
    public static function liquidite(Carbon $debut, Carbon $fin): ?float
    {
        if ($fin->copy()->addDays(self::JOURS_LIQUIDITE)->gt(now())) {
            return null;
        }

        $annonces = Listing::query()
            ->where('created_at', '>=', $debut)->where('created_at', '<', $fin)
            ->get(['id', 'created_at', 'status', 'updated_at']);
        if ($annonces->isEmpty()) {
            return null;
        }

        $premiereVente = Transaction::query()
            ->whereIn('status', TableauDeBord::STATUTS_VENDUS)
            ->whereIn('listing_id', $annonces->pluck('id'))
            ->selectRaw('listing_id, MIN(created_at) as vendue_at')
            ->groupBy('listing_id')
            ->pluck('vendue_at', 'listing_id');

        $vendues = $annonces->filter(function (Listing $annonce) use ($premiereVente) {
            // Sans vente enregistrée, une annonce « vendue » l'a été à sa dernière mise à jour.
            $vendueLe = isset($premiereVente[$annonce->id])
                ? Carbon::parse($premiereVente[$annonce->id])
                : ($annonce->status === 'sold' ? $annonce->updated_at : null);

            return $vendueLe && $vendueLe->lte($annonce->created_at->copy()->addDays(self::JOURS_LIQUIDITE));
        })->count();

        return round($vendues / $annonces->count() * 100, 1);
    }

    /**
     * Visiteurs humains uniques entre deux dates (null avant le comptage fiable).
     *
     * @return array{uniques:?int,par_jour:?float}
     */
    public static function visiteurs(Carbon $debut, Carbon $fin): array
    {
        $fiable = Carbon::parse(TableauDeBord::DEBUT_VISITEURS_FIABLES)->startOfDay();
        if (! Schema::hasTable('visiteurs_jours') || $fin->lte($fiable)) {
            return ['uniques' => null, 'par_jour' => null];
        }

        $de = $debut->gt($fiable) ? $debut->copy() : $fiable->copy();
        $a = $fin->copy()->subDay()->min(today());
        $jours = max(1, (int) $de->diffInDays($a) + 1);
        $base = DB::table('visiteurs_jours')->whereBetween('jour', [$de->toDateString(), $a->toDateString()]);

        return [
            'uniques' => (int) (clone $base)->distinct()->count('cle'),
            'par_jour' => round((clone $base)->count() / $jours, 1),
        ];
    }

    /**
     * Les 30 derniers jours, pour situer la plateforme.
     *
     * @return array<string,float|int|null>
     */
    public static function derniers30Jours(): array
    {
        $debut = now()->subDays(29)->startOfDay();
        $chiffres = TableauDeBord::entre($debut, null);
        $visiteurs = self::visiteurs($debut, now()->addDay()->startOfDay());

        return [
            'ventes' => (int) $chiffres['ventes'],
            'revenus' => (float) $chiffres['revenus'],
            'visiteurs_jour' => (float) ($visiteurs['par_jour'] ?? 0),
            // La liquidité se mesure sur les annonces assez anciennes : celles d'il y a 60 à 90 jours.
            'liquidite' => self::liquidite(now()->subDays(90)->startOfDay(), now()->subDays(self::JOURS_LIQUIDITE)->startOfDay()),
        ];
    }

    /**
     * Palier atteint et progression vers le suivant.
     *
     * @param  array<int,array<string,mixed>>|null  $mois
     * @return array{niveau:int,nom:string,emoji:string,suivant:?array,progression:array<int,array{libelle:string,actuel:float,objectif:float,pct:int,format:string}>}
     */
    public static function palier(?array $actuel = null, ?array $mois = null): array
    {
        $actuel ??= self::derniers30Jours();
        $mois ??= self::mois();

        // Mois complets passés au niveau « sérieuse », d'affilée jusqu'au dernier.
        $moisSerieux = 0;
        foreach (array_reverse(array_filter($mois, fn ($m) => ! $m['en_cours'])) as $m) {
            if ($m['ventes'] >= 3000 && ($m['visiteurs_jour'] ?? 0) >= 1000 && $m['revenus'] >= 5000) {
                $moisSerieux++;
            } else {
                break;
            }
        }
        $actuel['mois_serieux'] = $moisSerieux;

        $atteint = fn (int $niveau) => collect(self::PALIERS[$niveau]['objectifs'])
            ->every(fn ($objectif, $cle) => ($actuel[$cle] ?? 0) >= $objectif);

        $niveau = 1;
        foreach ([2, 3, 4] as $n) {
            if ($atteint($n)) {
                $niveau = $n;
            } else {
                break;
            }
        }

        $libelles = [
            'ventes' => ['Ventes payées en ligne sur 30 jours', 'nombre'],
            'liquidite' => ['Annonces vendues en moins de 60 jours', 'pct'],
            'visiteurs_jour' => ['Visiteurs humains par jour', 'nombre'],
            'revenus' => ['Revenus Swap’Îles sur 30 jours', 'euros'],
            'mois_serieux' => ['Mois d’affilée au niveau « sérieuse »', 'nombre'],
        ];

        $suivant = self::PALIERS[$niveau + 1] ?? null;
        $progression = [];
        foreach ($suivant['objectifs'] ?? [] as $cle => $objectif) {
            $valeur = (float) ($actuel[$cle] ?? 0);
            $progression[] = [
                'libelle' => $libelles[$cle][0],
                'actuel' => $valeur,
                'objectif' => (float) $objectif,
                'pct' => (int) min(100, floor($valeur / $objectif * 100)),
                'format' => $libelles[$cle][1],
            ];
        }

        return [
            'niveau' => $niveau,
            'nom' => self::PALIERS[$niveau]['nom'],
            'emoji' => self::PALIERS[$niveau]['emoji'],
            'suivant' => $suivant ? ['niveau' => $niveau + 1] + $suivant : null,
            'progression' => $progression,
        ];
    }

    /** Fichier CSV (séparateur « ; », lisible par Excel en français). */
    public static function csv(array $mois): string
    {
        $nombre = fn ($v) => $v === null ? '' : str_replace('.', ',', (string) $v);
        $lignes = [[
            'Mois', 'Visiteurs uniques', 'Visiteurs par jour', 'Nouveaux membres', 'Annonces créées',
            'Ventes payées en ligne', 'Volume (€)', 'Revenus Swap’Îles (€)', 'Panier moyen (€)',
            'Ventes main propre déclarées', 'Acheteurs', 'Acheteurs qui reviennent (%)', 'Annonces vendues en 60 j (%)',
        ]];
        foreach ($mois as $m) {
            $lignes[] = [
                $m['libelle'] . ($m['en_cours'] ? ' (en cours)' : ''),
                $nombre($m['visiteurs']), $nombre($m['visiteurs_jour']), $m['inscriptions'], $m['annonces'],
                $m['ventes'], $nombre($m['volume']), $nombre($m['revenus']), $nombre($m['panier_moyen']),
                $m['ventes_declarees'], $m['acheteurs'], $nombre($m['fidelite']), $nombre($m['liquidite']),
            ];
        }

        $sortie = "\u{FEFF}";
        foreach ($lignes as $ligne) {
            $sortie .= implode(';', array_map(fn ($c) => '"' . str_replace('"', '""', (string) $c) . '"', $ligne)) . "\r\n";
        }

        return $sortie;
    }

    public static function evolution(float|int|null $maintenant, float|int|null $avant): ?int
    {
        if ($maintenant === null || $avant === null || $avant <= 0) {
            return null;
        }

        return (int) round(($maintenant - $avant) / $avant * 100);
    }
}

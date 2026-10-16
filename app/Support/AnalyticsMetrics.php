<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chiffres de la page « Analyse avancée ».
 *
 * Mêmes sources et mêmes règles que le tableau de bord et le calendrier :
 *  - visiteurs = humains confirmés par leur navigateur (visiteurs_jours),
 *    jamais le trafic brut du serveur, gonflé par les robots ;
 *  - argent = TableauDeBord::entre (ventes payées en ligne, revenus nets) ;
 *  - pages vues = celles des seuls visiteurs humains confirmés.
 */
class AnalyticsMetrics
{
    /** Mêmes périodes que le tableau de bord. */
    public static function periods(): array
    {
        return TableauDeBord::PERIODES;
    }

    private static function aDesVisiteurs(): bool
    {
        return Schema::hasTable('visiteurs_jours');
    }

    /** Heure (0-23) d'une colonne date, quel que soit le moteur SQL. */
    private static function heureSql(string $colonne): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', {$colonne}) AS INTEGER)"
            : "HOUR({$colonne})";
    }

    // --- Aujourd'hui ---------------------------------------------------------

    /**
     * La journée en cours : visiteurs humains, connectés, heure par heure.
     *
     * @return array<string,mixed>
     */
    public static function aujourdhui(): array
    {
        $parHeure = array_fill(0, 24, 0);
        $visiteurs = $membres = $appli = 0;
        $hierMemeHeure = null;

        if (self::aDesVisiteurs()) {
            $jour = DB::table('visiteurs_jours')->where('jour', today()->toDateString());
            $visiteurs = (clone $jour)->count();
            $membres = (clone $jour)->whereNotNull('user_id')->count();
            $appli = (clone $jour)->where('appli', true)->count();

            $lignes = (clone $jour)->whereNotNull('arrivee_at')
                ->selectRaw(self::heureSql('arrivee_at') . ' as h, COUNT(*) as n')
                ->groupBy('h')->pluck('n', 'h');
            foreach ($lignes as $h => $n) {
                $parHeure[(int) $h] = (int) $n;
            }

            // Hier à la même heure : une comparaison juste en cours de journée.
            $hier = DB::table('visiteurs_jours')->where('jour', today()->subDay()->toDateString());
            if ((clone $hier)->whereNotNull('arrivee_at')->exists()) {
                $hierMemeHeure = (clone $hier)->where('arrivee_at', '<=', now()->subDay())->count();
            }
        }

        return [
            'en_ligne' => VisiteursEnDirect::compte()['humains'],
            'visiteurs' => $visiteurs,
            'membres' => $membres,
            'appli' => $appli,
            'hier_meme_heure' => $hierMemeHeure,
            'par_heure' => $parHeure,
            'connectes' => self::connectesDuJour(),
        ];
    }

    /**
     * Courbe des connectés (humains) relevés chaque minute aujourd'hui.
     *
     * @return array{labels:array<int,string>,data:array<int,int>,pic:array{nombre:int,heure:?string}}
     */
    public static function connectesDuJour(): array
    {
        $labels = $data = [];
        $pic = ['nombre' => 0, 'heure' => null];

        if (! Schema::hasTable('visitor_snapshots')) {
            return ['labels' => $labels, 'data' => $data, 'pic' => $pic];
        }

        $releves = DB::table('visitor_snapshots')
            ->where('created_at', '>=', today())
            ->orderBy('created_at')
            ->get(['live_count', 'created_at']);

        // Un point toutes les 10 minutes suffit à lire la journée.
        foreach ($releves->groupBy(fn ($r) => Carbon::parse($r->created_at)->format('H') . ':' . intdiv((int) Carbon::parse($r->created_at)->format('i'), 10)) as $groupe) {
            $max = (int) $groupe->max('live_count');
            $heure = Carbon::parse($groupe->first()->created_at)->format('H:i');
            $labels[] = $heure;
            $data[] = $max;
        }

        foreach ($releves as $r) {
            if ((int) $r->live_count > $pic['nombre']) {
                $pic = ['nombre' => (int) $r->live_count, 'heure' => Carbon::parse($r->created_at)->format('H:i')];
            }
        }

        return ['labels' => $labels, 'data' => $data, 'pic' => $pic];
    }

    // --- Sur la période ------------------------------------------------------

    /**
     * Toutes les métriques d'une période (ou d'une plage de dates).
     *
     * @return array<string,mixed>
     */
    public static function advanced(?string $period, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $custom = $from !== null || $to !== null;
        if (! $custom && ! array_key_exists((string) $period, TableauDeBord::PERIODES)) {
            $period = '30j';
        }
        $debut = $custom ? $from?->copy()->startOfDay() : TableauDeBord::debut($period);
        $fin = $to?->copy()->endOfDay();
        $finExclue = $fin?->copy()->addSecond();

        $dansPeriode = fn ($q, string $colonne = 'created_at') => $q
            ->when($debut, fn ($q) => $q->where($colonne, '>=', $debut))
            ->when($fin, fn ($q) => $q->where($colonne, '<=', $fin));

        // --- Argent : exactement le calcul du tableau de bord ---
        $chiffres = TableauDeBord::entre($debut, $finExclue);
        $enLigne = $dansPeriode(Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS)->enLigne());
        // Volume = ce que les acheteurs ont payé : carte + crédit parrainage.
        $credit = (float) (clone $enLigne)->sum('credit_utilise');
        $gmv = (float) $chiffres['volume'] + $credit;
        $ventes = (int) $chiffres['ventes'];
        $revenus = (float) $chiffres['revenus'];

        $vendues = $dansPeriode(Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS));
        $acheteurs = (clone $enLigne)->distinct()->count('buyer_id');
        $vendeurs = (clone $vendues)->distinct()->count('seller_id');

        // --- Visiteurs humains ---
        $visiteurs = self::visiteurs($debut, $fin);

        // --- Pages vues des seuls humains ---
        $pages = self::pagesHumaines($visiteurs['debut_fiable'], $fin);

        // --- Entonnoir par cohorte : les inscrits de la période, et ce qu'ILS ont fait ---
        $inscrits = $dansPeriode(User::query());
        $nbInscrits = (clone $inscrits)->count();
        $ontPublie = (clone $inscrits)->whereHas('listings')->count();
        $ontVendu = (clone $inscrits)->whereIn('id', Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS)->select('seller_id'))->count();
        $ontAchete = (clone $inscrits)->whereIn('id', Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS)->enLigne()->select('buyer_id'))->count();

        // --- Écoulement : annonces créées sur la période et déjà vendues ---
        $annoncesPeriode = $dansPeriode(Listing::query());
        $nbAnnonces = (clone $annoncesPeriode)->count();
        $annoncesVendues = (clone $annoncesPeriode)->where(function ($q) {
            $q->where('status', 'sold')
                ->orWhereIn('id', Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS)->select('listing_id'));
        })->count();

        return [
            'period' => $custom ? 'custom' : $period,
            'periodLabel' => $custom
                ? 'Du ' . ($debut ? $debut->format('d/m/Y') : '…') . ' au ' . ($fin ? $fin->format('d/m/Y') : 'aujourd’hui')
                : (TableauDeBord::PERIODES[$period] ?? '30 derniers jours'),
            'from' => $debut?->toDateString(),
            'to' => $to?->toDateString(),
            'isCustom' => $custom,

            // Argent (identique au tableau de bord)
            'gmv' => $gmv,
            'credit' => $credit,
            'paidCount' => $ventes,
            'aov' => $ventes > 0 ? $gmv / $ventes : 0.0,
            'netRevenue' => $revenus,
            'takeRate' => $gmv > 0 ? $revenus / $gmv * 100 : 0.0,
            'ventesDeclarees' => (int) $chiffres['ventes_declarees'],
            'volumeDeclare' => (float) $chiffres['volume_declare'],
            'activeBuyers' => $acheteurs,
            'activeSellers' => $vendeurs,
            'arpu' => $acheteurs > 0 ? $revenus / $acheteurs : 0.0,

            // Activité
            'newUsers' => (int) $chiffres['inscriptions'],
            'newListings' => (int) $chiffres['annonces'],
            'messages' => (int) $chiffres['messages'],
            'annoncesVendues' => $annoncesVendues,
            'sellThrough' => $nbAnnonces > 0 ? round($annoncesVendues / $nbAnnonces * 100, 1) : 0.0,

            // Visiteurs
            'visiteurs' => $visiteurs,
            'pages' => $pages,

            'funnel' => [
                'visiteurs' => $visiteurs['uniques'],
                'inscrits' => $nbInscrits,
                'publie' => $ontPublie,
                'vendu' => $ontVendu,
                'achete' => $ontAchete,
            ],
        ];
    }

    /**
     * Visiteurs humains de la période. Avant le comptage fiable, il n'y a
     * rien : la période est raccourcie et la page le signale.
     *
     * @return array<string,mixed>
     */
    public static function visiteurs(?Carbon $debut, ?Carbon $fin): array
    {
        $fiable = Carbon::parse(TableauDeBord::DEBUT_VISITEURS_FIABLES)->startOfDay();
        $debutFiable = $debut && $debut->gt($fiable) ? $debut->copy() : $fiable->copy();
        $finJour = ($fin && $fin->lt(now()) ? $fin->copy() : now())->startOfDay();
        $nbJours = $debutFiable->gt($finJour) ? 0 : (int) $debutFiable->copy()->startOfDay()->diffInDays($finJour) + 1;

        $vide = [
            'uniques' => 0, 'visites' => 0, 'par_jour' => 0.0, 'jours' => $nbJours,
            'tronque' => $debut === null || $debut->lt($fiable), 'debut_fiable' => $debutFiable,
            'membres' => 0, 'appli' => 0, 'fideles' => 0, 'fideles_pct' => 0.0,
            'sources' => [], 'iles' => [], 'par_heure' => array_fill(0, 24, 0),
            'serie' => ['labels' => [], 'data' => []],
            'actifs_jour' => 0, 'actifs_semaine' => 0, 'actifs_mois' => 0, 'stickiness' => 0.0,
        ];

        if (! self::aDesVisiteurs() || $nbJours === 0) {
            return $vide;
        }

        $base = DB::table('visiteurs_jours')
            ->where('jour', '>=', $debutFiable->toDateString())
            ->where('jour', '<=', $finJour->toDateString());

        $uniques = (int) (clone $base)->distinct()->count('cle');
        $visites = (int) (clone $base)->count();
        $fideles = (int) DB::query()->fromSub(
            (clone $base)->select('cle')->groupBy('cle')->havingRaw('COUNT(*) >= 2'), 'f'
        )->count();

        $sources = (clone $base)->selectRaw('source, COUNT(*) as n')->groupBy('source')->pluck('n', 'source');
        $libellesSources = [];
        foreach ($sources as $cle => $n) {
            $libellesSources[VisiteursEnDirect::SOURCES[$cle] ?? ucfirst((string) $cle)] = (int) $n;
        }
        arsort($libellesSources);

        $iles = (clone $base)->selectRaw("COALESCE(NULLIF(territoire, ''), 'Non précisée') as ile, COUNT(*) as n")
            ->groupBy('ile')->orderByDesc('n')->pluck('n', 'ile')->map(fn ($n) => (int) $n)->all();

        $parHeure = array_fill(0, 24, 0);
        foreach ((clone $base)->whereNotNull('arrivee_at')->selectRaw(self::heureSql('arrivee_at') . ' as h, COUNT(*) as n')->groupBy('h')->pluck('n', 'h') as $h => $n) {
            $parHeure[(int) $h] = (int) $n;
        }

        // Courbe jour par jour (≤ 120 jours affichés).
        $parJour = (clone $base)->selectRaw('jour, COUNT(*) as n')->groupBy('jour')->pluck('n', 'jour');
        $labels = $data = [];
        $curseur = $nbJours > 120 ? $finJour->copy()->subDays(119) : $debutFiable->copy()->startOfDay();
        while ($curseur->lte($finJour)) {
            $cle = $curseur->toDateString();
            $labels[] = $curseur->format('d/m');
            $data[] = (int) ($parJour[$cle] ?? $parJour[$cle . ' 00:00:00'] ?? 0);
            $curseur->addDay();
        }

        // Actifs glissants (indépendants de la période choisie).
        $depuis = fn (int $jours) => max(today()->subDays($jours - 1)->toDateString(), $fiable->toDateString());
        $actifs = fn (int $jours) => (int) DB::table('visiteurs_jours')
            ->where('jour', '>=', $depuis($jours))->where('jour', '<=', today()->toDateString())
            ->distinct()->count('cle');
        $actifsMois = $actifs(30);
        $visites30 = (int) DB::table('visiteurs_jours')->where('jour', '>=', $depuis(30))->where('jour', '<=', today()->toDateString())->count();
        $jours30 = max(1, min(30, (int) $fiable->diffInDays(today()) + 1));

        return [
            'uniques' => $uniques,
            'visites' => $visites,
            'par_jour' => round($visites / max(1, $nbJours), 1),
            'jours' => $nbJours,
            'tronque' => $vide['tronque'],
            'debut_fiable' => $debutFiable,
            'membres' => (int) (clone $base)->whereNotNull('user_id')->distinct()->count('user_id'),
            'appli' => (int) (clone $base)->where('appli', true)->distinct()->count('cle'),
            'fideles' => $fideles,
            'fideles_pct' => $uniques > 0 ? round($fideles / $uniques * 100, 1) : 0.0,
            'sources' => $libellesSources,
            'iles' => $iles,
            'par_heure' => $parHeure,
            'serie' => ['labels' => $labels, 'data' => $data],
            'actifs_jour' => $actifs(1),
            'actifs_semaine' => $actifs(7),
            'actifs_mois' => $actifsMois,
            // Part des visiteurs du mois qui viennent un jour donné, en moyenne.
            'stickiness' => $actifsMois > 0 ? round($visites30 / $jours30 / $actifsMois * 100, 1) : 0.0,
        ];
    }

    /**
     * Pages vues par les visiteurs humains confirmés : le cookie visiteur du
     * suivi des pages est relié à la présence confirmée par le navigateur.
     *
     * @return array{vues:int,par_visite:float,top:array<string,int>}
     */
    public static function pagesHumaines(Carbon $debut, ?Carbon $fin): array
    {
        $resultat = ['vues' => 0, 'par_visite' => 0.0, 'top' => []];

        if (! self::aDesVisiteurs() || ! Schema::hasTable('analytics_events')) {
            return $resultat;
        }

        $evenements = DB::table('analytics_events')
            ->where('created_at', '>=', $debut->copy()->startOfDay())
            ->when($fin, fn ($q) => $q->where('created_at', '<=', $fin))
            ->whereNotNull('visitor_id');

        $humains = DB::table('visiteurs_jours')
            ->where('jour', '>=', $debut->toDateString())
            ->when($fin, fn ($q) => $q->where('jour', '<=', $fin->toDateString()))
            ->distinct()->pluck('cle')->flip();

        $cle = fn (string $vid) => hash('sha256', 'presence|' . $vid . config('app.key'));
        $ids = (clone $evenements)->distinct()->pluck('visitor_id')
            ->filter(fn ($vid) => isset($humains[$cle((string) $vid)]))
            ->values();

        $vues = 0;
        $top = [];
        $visites = 0;
        foreach ($ids->chunk(800) as $paquet) {
            $q = (clone $evenements)->whereIn('visitor_id', $paquet->all());
            $vues += (clone $q)->count();
            $visites += (int) DB::query()->fromSub((clone $q)->selectRaw('visitor_id, DATE(created_at) as j')->groupBy('visitor_id', 'j'), 'v')->count();
            foreach ((clone $q)->selectRaw('COALESCE(page_name, path) as page, COUNT(*) as n')->groupBy('page')->pluck('n', 'page') as $page => $n) {
                $top[$page] = ($top[$page] ?? 0) + (int) $n;
            }
        }
        arsort($top);

        return [
            'vues' => $vues,
            'par_visite' => $visites > 0 ? round($vues / $visites, 1) : 0.0,
            'top' => array_slice($top, 0, 10, true),
        ];
    }

    /**
     * Inscriptions, annonces et ventes payées en ligne, jour par jour.
     *
     * @return array{labels:array<int,string>,inscriptions:array<int,int>,annonces:array<int,int>,ventes:array<int,int>}
     */
    public static function activiteParJour(Carbon $debut, Carbon $fin): array
    {
        $debut = $debut->copy()->startOfDay();
        $fin = $fin->copy()->startOfDay();
        if ($debut->diffInDays($fin) > 119) {
            $debut = $fin->copy()->subDays(119);
        }

        $compter = fn ($requete) => $requete
            ->where('created_at', '>=', $debut)
            ->where('created_at', '<', $fin->copy()->addDay())
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')
            ->groupBy('d')->pluck('n', 'd');

        $series = [
            'inscriptions' => $compter(User::query()->toBase()),
            'annonces' => $compter(Listing::query()->toBase()),
            'ventes' => $compter(TableauDeBord::ventes()()->toBase()),
        ];

        $sortie = ['labels' => [], 'inscriptions' => [], 'annonces' => [], 'ventes' => []];
        for ($d = $debut->copy(); $d->lte($fin); $d->addDay()) {
            $cle = $d->toDateString();
            $sortie['labels'][] = $d->format('d/m');
            foreach ($series as $nom => $valeurs) {
                $sortie[$nom][] = (int) ($valeurs[$cle] ?? 0);
            }
        }

        return $sortie;
    }
}

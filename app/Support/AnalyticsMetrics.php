<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calculs des métriques produit / SaaS à partir du flux d'événements
 * (analytics_events) et des tables métier (users, listings, transactions).
 *
 * Regroupe la logique utilisée par le tableau de bord et la page « Analyse
 * avancée » afin d'avoir des chiffres cohérents partout.
 */
class AnalyticsMetrics
{
    /** Options de période partagées dans tout l'admin. */
    public static function periods(): array
    {
        return [
            'today' => 'Aujourd’hui',
            'week' => 'Cette semaine',
            '15d' => '15 derniers jours',
            '30d' => '30 derniers jours',
            '3m' => '3 mois',
            'all' => 'Depuis le début',
        ];
    }

    public static function startDate(string $period): ?Carbon
    {
        return match ($period) {
            'today' => Carbon::today(),
            'week' => Carbon::now()->startOfWeek(),
            '15d' => Carbon::now()->subDays(15),
            '30d' => Carbon::now()->subDays(30),
            '3m' => Carbon::now()->subMonths(3),
            default => null,
        };
    }

    /** Nombre de jours affichés dans les courbes d'évolution pour une période. */
    public static function chartDays(string $period): int
    {
        return match ($period) {
            'today' => 14,
            'week' => 14,
            '15d' => 15,
            '30d' => 30,
            '3m' => 90,
            default => 30,
        };
    }

    private static function eventsTableExists(): bool
    {
        return Schema::hasTable('analytics_events');
    }

    /**
     * Série journalière remplie (jours sans données = 0) pour un modèle donné.
     *
     * @return array{labels: array<int,string>, data: array<int,int>}
     */
    public static function dailySeries(string $modelClass, int $days, ?callable $constrain = null): array
    {
        $start = Carbon::today()->subDays($days - 1);

        $query = $modelClass::query()->where('created_at', '>=', $start);
        if ($constrain) {
            $query = $constrain($query);
        }

        $rows = $query
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $labels = [];
        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $date = (clone $start)->addDays($i);
            $key = $date->toDateString();
            $labels[] = $date->format('d/m');
            $data[] = (int) ($rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Série journalière entre deux dates (bornes incluses), trous remplis à 0.
     *
     * @return array{labels: array<int,string>, data: array<int,int>}
     */
    public static function dailySeriesBetween(string $modelClass, Carbon $start, Carbon $end, ?callable $constrain = null): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();

        // Sécurité : on borne à 366 jours pour éviter un graphique démesuré.
        if ($start->diffInDays($end) > 366) {
            $start = $end->copy()->subDays(366);
        }

        $query = $modelClass::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end->copy()->addDay());
        if ($constrain) {
            $query = $constrain($query);
        }

        $rows = $query
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $labels = [];
        $data = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('d/m');
            $data[] = (int) ($rows[$key] ?? 0);
            $cursor->addDay();
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * Visiteurs uniques (sessions) par heure aujourd'hui.
     *
     * @return array<int,int> index 0-23
     */
    public static function todayHourlyVisitors(): array
    {
        $hours = array_fill(0, 24, 0);

        if (! self::eventsTableExists()) {
            return $hours;
        }

        try {
            $rows = DB::table('analytics_events')
                ->whereDate('created_at', Carbon::today())
                ->selectRaw('HOUR(created_at) as h, COUNT(DISTINCT session_id) as c')
                ->groupBy('h')
                ->pluck('c', 'h');

            foreach ($rows as $h => $c) {
                $hours[(int) $h] = (int) $c;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $hours;
    }

    /**
     * Courbe des connectés simultanés aujourd'hui + pic du jour.
     *
     * @return array{labels:array<int,string>,data:array<int,int>,peak:array{count:int,time:?string}}
     */
    public static function todayConcurrent(): array
    {
        $labels = [];
        $data = [];
        $peak = ['count' => 0, 'time' => null];

        if (! Schema::hasTable('visitor_snapshots')) {
            return ['labels' => $labels, 'data' => $data, 'peak' => $peak];
        }

        $rows = DB::table('visitor_snapshots')
            ->whereDate('created_at', Carbon::today())
            ->orderBy('created_at')
            ->get(['live_count', 'created_at']);

        foreach ($rows as $r) {
            $t = Carbon::parse($r->created_at);
            $labels[] = $t->format('H:i');
            $data[] = (int) $r->live_count;

            if ((int) $r->live_count > $peak['count']) {
                $peak = ['count' => (int) $r->live_count, 'time' => $t->format('H:i')];
            }
        }

        return ['labels' => $labels, 'data' => $data, 'peak' => $peak];
    }

    /**
     * Bloc complet de métriques pour la page d'analyse avancée.
     *
     * @param  Carbon|null  $from  Date de début personnalisée (prioritaire sur $period).
     * @param  Carbon|null  $to    Date de fin personnalisée (incluse).
     */
    public static function advanced(string $period, ?Carbon $from = null, ?Carbon $to = null): array
    {
        // Une plage personnalisée (date à date) est prioritaire sur la période.
        $custom = $from !== null || $to !== null;
        $start = $custom ? ($from?->copy()->startOfDay()) : self::startDate($period);
        $end = $to?->copy()->endOfDay();

        $hasEvents = self::eventsTableExists();

        // --- Helper de filtrage par période (bornes début ET fin) ---
        $inPeriod = function ($q) use ($start, $end) {
            if ($start) {
                $q->where('created_at', '>=', $start);
            }
            if ($end) {
                $q->where('created_at', '<=', $end);
            }

            return $q;
        };

        // ============ ACQUISITION ============
        $newUsers = $inPeriod(User::query())->count();
        $newListings = $inPeriod(Listing::query())->count();

        // ============ ÉVÉNEMENTS / ENGAGEMENT ============
        $pageViews = 0;
        $sessions = 0;
        $uniqueVisitors = 0;
        $bounceRate = 0.0;
        $pagesPerSession = 0.0;
        $dau = $wau = $mau = 0;
        $returningVisitors = 0;
        $hourly = array_fill(0, 24, 0);
        $devices = collect();
        $topPages = collect();
        $sources = [];

        if ($hasEvents) {
          try {
            $base = DB::table('analytics_events');
            if ($start) {
                $base->where('created_at', '>=', $start);
            }
            if ($end) {
                $base->where('created_at', '<=', $end);
            }

            $pageViews = (clone $base)->count();

            // Sessions = session_id distincts
            $sessions = (clone $base)->whereNotNull('session_id')->distinct()->count('session_id');
            $uniqueVisitors = $sessions;

            $pagesPerSession = $sessions > 0 ? round($pageViews / $sessions, 1) : 0.0;

            // Rebond : sessions à 1 seul événement
            $singleEventSessions = (clone $base)
                ->whereNotNull('session_id')
                ->select('session_id')
                ->groupBy('session_id')
                ->havingRaw('COUNT(*) = 1')
                ->get()
                ->count();
            $bounceRate = $sessions > 0 ? round($singleEventSessions / $sessions * 100, 1) : 0.0;

            // Visiteurs récurrents : sessions actives sur ≥ 2 jours distincts
            $returningVisitors = DB::table('analytics_events')
                ->whereNotNull('session_id')
                ->when($start, fn ($q) => $q->where('created_at', '>=', $start))
                ->when($end, fn ($q) => $q->where('created_at', '<=', $end))
                ->select('session_id')
                ->groupBy('session_id')
                ->havingRaw('COUNT(DISTINCT DATE(created_at)) >= 2')
                ->get()
                ->count();

            // Actifs glissants (indépendants de la période)
            $dau = DB::table('analytics_events')->where('created_at', '>=', Carbon::now()->subDay())->distinct()->count('session_id');
            $wau = DB::table('analytics_events')->where('created_at', '>=', Carbon::now()->subDays(7))->distinct()->count('session_id');
            $mau = DB::table('analytics_events')->where('created_at', '>=', Carbon::now()->subDays(30))->distinct()->count('session_id');

            // Activité par heure
            $hourRows = (clone $base)
                ->selectRaw('HOUR(created_at) as h, COUNT(*) as c')
                ->groupBy('h')
                ->pluck('c', 'h');
            foreach ($hourRows as $h => $c) {
                $hourly[(int) $h] = (int) $c;
            }

            // Répartition appareils
            $devices = (clone $base)
                ->selectRaw('COALESCE(device, "inconnu") as device, COUNT(*) as c')
                ->groupBy('device')
                ->orderByDesc('c')
                ->get();

            // Pages les plus vues
            $topPages = (clone $base)
                ->selectRaw('COALESCE(page_name, path) as label, path, COUNT(*) as c')
                ->groupBy('label', 'path')
                ->orderByDesc('c')
                ->limit(10)
                ->get();

            // Sources de trafic (referer -> domaine)
            $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'swapiles.com';
            $direct = (clone $base)->where(function ($q) {
                $q->whereNull('referer')->orWhere('referer', '');
            })->count();

            $refRows = (clone $base)
                ->whereNotNull('referer')->where('referer', '!=', '')
                ->selectRaw('referer, COUNT(*) as c')
                ->groupBy('referer')
                ->orderByDesc('c')
                ->limit(300)
                ->get();

            $agg = [];
            foreach ($refRows as $r) {
                $rh = parse_url($r->referer, PHP_URL_HOST) ?: 'autre';
                $rh = preg_replace('/^www\./', '', strtolower($rh));
                if ($rh === $host || $rh === preg_replace('/^www\./', '', $host)) {
                    $direct += (int) $r->c; // navigation interne comptée comme direct
                    continue;
                }
                $agg[$rh] = ($agg[$rh] ?? 0) + (int) $r->c;
            }
            arsort($agg);
            $sources = ['Direct' => $direct] + array_slice($agg, 0, 8, true);
            $sources = array_filter($sources, fn ($v) => $v > 0);
            arsort($sources);
          } catch (\Throwable $e) {
              // En cas d'incompatibilité SQL ou d'erreur, on n'empêche jamais
              // l'affichage de la page : les métriques d'audience restent à 0.
              report($e);
          }
        }

        // ============ CONVERSION (funnel marketplace) ============
        $publishers = $inPeriod(Listing::query())->distinct()->count('user_id');
        $sellers = $inPeriod(Transaction::whereIn('status', ['paid', 'completed']))->distinct()->count('seller_id');

        // ============ REVENU / MARKETPLACE ============
        $paid = $inPeriod(Transaction::whereIn('status', ['paid', 'completed']));
        $gmv = (float) (clone $paid)->sum('amount');
        $commission = (float) (clone $paid)->sum('commission');
        $protection = (float) (clone $paid)->sum('buyer_protection_fee');
        $netRevenue = $commission + $protection;
        $paidCount = (clone $paid)->count();
        $aov = $paidCount > 0 ? $gmv / $paidCount : 0.0;
        $takeRate = $gmv > 0 ? $netRevenue / $gmv * 100 : 0.0;

        $activeSellers = $sellers;
        $activeBuyers = $inPeriod(Transaction::whereIn('status', ['paid', 'completed']))->distinct()->count('buyer_id');

        $listingsPublishedNow = Listing::where('status', 'published')->count();
        $listingsSold = Listing::where('status', 'sold')->count();
        $sellThrough = ($listingsPublishedNow + $listingsSold) > 0
            ? round($listingsSold / ($listingsPublishedNow + $listingsSold) * 100, 1)
            : 0.0;

        $arpu = $newUsers > 0 ? $netRevenue / max($activeBuyers, 1) : 0.0;

        return [
            'period' => $custom ? 'custom' : $period,
            'periodLabel' => $custom
                ? 'Du ' . ($start ? $start->format('d/m/Y') : '…') . ' au ' . ($end ? $end->format('d/m/Y') : "aujourd'hui")
                : (self::periods()[$period] ?? 'Depuis le début'),
            'from' => $start?->toDateString(),
            'to' => $to?->toDateString(),
            'isCustom' => $custom,

            // Acquisition
            'newUsers' => $newUsers,
            'newListings' => $newListings,
            'sources' => $sources,

            // Engagement
            'pageViews' => $pageViews,
            'sessions' => $sessions,
            'uniqueVisitors' => $uniqueVisitors,
            'pagesPerSession' => $pagesPerSession,
            'bounceRate' => $bounceRate,
            'dau' => $dau,
            'wau' => $wau,
            'mau' => $mau,
            'stickiness' => $mau > 0 ? round($dau / $mau * 100, 1) : 0.0,
            'returningVisitors' => $returningVisitors,
            'returningRate' => $sessions > 0 ? round($returningVisitors / $sessions * 100, 1) : 0.0,
            'hourly' => $hourly,
            'devices' => $devices,
            'topPages' => $topPages,

            // Conversion funnel
            'funnel' => [
                'visitors' => $uniqueVisitors,
                'signups' => $newUsers,
                'publishers' => $publishers,
                'sellers' => $sellers,
            ],

            // Revenu / marketplace
            'gmv' => $gmv,
            'netRevenue' => $netRevenue,
            'commission' => $commission,
            'protection' => $protection,
            'paidCount' => $paidCount,
            'aov' => $aov,
            'takeRate' => $takeRate,
            'arpu' => $arpu,
            'activeSellers' => $activeSellers,
            'activeBuyers' => $activeBuyers,
            'listingsPublishedNow' => $listingsPublishedNow,
            'listingsSold' => $listingsSold,
            'sellThrough' => $sellThrough,

            'hasEvents' => $hasEvents,
        ];
    }
}

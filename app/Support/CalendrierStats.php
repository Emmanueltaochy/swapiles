<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\LiveSession;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Calendrier de l'admin : un mois d'un coup d'œil, puis le détail d'une
 * journée (audience, activité, argent). Mêmes règles de calcul que le
 * tableau de bord (TableauDeBord::entre).
 */
class CalendrierStats
{
    /**
     * Petits chiffres de chaque jour du mois.
     *
     * @return array<string,array{visiteurs:?int,inscriptions:int,annonces:int,ventes:int}>
     */
    public static function mois(Carbon $mois): array
    {
        $debut = $mois->copy()->startOfMonth();
        $fin = $mois->copy()->endOfMonth();

        $parJour = fn ($requete, string $colonne = 'created_at') => $requete
            ->whereBetween($colonne, [$debut, $fin])
            ->selectRaw("DATE({$colonne}) as jour, COUNT(*) as n")
            ->groupBy('jour')->pluck('n', 'jour');

        $inscriptions = $parJour(User::query()->toBase());
        $annonces = $parJour(Listing::query()->toBase());
        $ventes = $parJour(Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS)->toBase());
        $visiteurs = Schema::hasTable('visiteurs_jours')
            ? DB::table('visiteurs_jours')->whereBetween('jour', [$debut->toDateString(), $fin->toDateString()])
                ->selectRaw('jour, COUNT(*) as n')->groupBy('jour')->pluck('n', 'jour')
            : collect();

        $jours = [];
        for ($d = $debut->copy(); $d->lte($fin); $d->addDay()) {
            $cle = $d->toDateString();
            $jours[$cle] = [
                // Avant le comptage fiable (robots exclus), on n'affiche rien.
                'visiteurs' => $cle >= TableauDeBord::DEBUT_VISITEURS_FIABLES && $d->lte(now()) ? (int) ($visiteurs[$cle] ?? 0) : null,
                'inscriptions' => (int) ($inscriptions[$cle] ?? 0),
                'annonces' => (int) ($annonces[$cle] ?? 0),
                'ventes' => (int) ($ventes[$cle] ?? 0),
            ];
        }

        return $jours;
    }

    /** Détail d'une journée. */
    public static function jour(Carbon $jour): array
    {
        $debut = $jour->copy()->startOfDay();
        $fin = $debut->copy()->addDay();
        $entre = fn ($q, string $colonne = 'created_at') => $q->where($colonne, '>=', $debut)->where($colonne, '<', $fin);

        $chiffres = TableauDeBord::entre($debut, $fin);
        $semainePassee = TableauDeBord::entre($debut->copy()->subWeek(), $fin->copy()->subWeek());

        $fiable = $debut->toDateString() >= TableauDeBord::DEBUT_VISITEURS_FIABLES;
        $visiteurs = fn (Carbon $j) => $fiable && Schema::hasTable('visiteurs_jours')
            ? DB::table('visiteurs_jours')->where('jour', $j->toDateString()) : null;
        $v = $visiteurs($debut);

        $installations = Schema::hasTable('app_installations')
            ? $entre(DB::table('app_installations'), 'premiere_ouverture_at')
                ->selectRaw('platform, COUNT(*) as n')->groupBy('platform')->pluck('n', 'platform')
            : collect();

        $push = Schema::hasTable('push_envois') ? PushSuivi::bilan($debut, $fin->copy()->subSecond()) : null;

        return $chiffres + [
            'date' => $debut,
            'semaine_passee' => $semainePassee,
            'visiteurs' => $v ? (clone $v)->count() : null,
            'visiteurs_appli' => $v ? (clone $v)->where('appli', true)->count() : null,
            'visiteurs_membres' => $v ? (clone $v)->whereNotNull('user_id')->count() : null,
            'visiteurs_semaine_passee' => ($w = $visiteurs($debut->copy()->subWeek())) ? $w->count() : null,
            'installations_ios' => (int) ($installations['ios'] ?? 0),
            'installations_android' => (int) ($installations['android'] ?? 0),
            'push' => $push,
            // Favoris des AUTRES membres (pas ses propres annonces mises de côté).
            'favoris' => $entre(DB::table('favorites')->join('listings', 'listings.id', '=', 'favorites.listing_id'), 'favorites.created_at')
                ->whereColumn('favorites.user_id', '!=', 'listings.user_id')->count(),
            'lives' => Schema::hasTable('live_sessions') ? $entre(LiveSession::query(), 'demarre_at')->count() : 0,
            'nouveaux_membres' => $entre(User::query())->latest()->limit(30)->get(['id', 'name', 'email', 'territoire', 'created_at']),
            'liste_ventes' => $entre(Transaction::query()->whereIn('status', TableauDeBord::STATUTS_VENDUS))
                ->with(['listing:id,title', 'buyer:id,name', 'seller:id,name'])->latest()->limit(50)->get(),
        ];
    }

    /** Évolution en % par rapport à la même journée la semaine précédente. */
    public static function evolution(?float $maintenant, ?float $avant): ?int
    {
        if ($maintenant === null || $avant === null || $avant <= 0) {
            return null;
        }

        return (int) round(($maintenant - $avant) / $avant * 100);
    }
}

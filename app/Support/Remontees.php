<?php

namespace App\Support;

use App\Models\Listing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remontées d'annonces : pour que les annonces plus anciennes ne finissent pas
 * oubliées en bas de la liste, sans laisser les vendeurs tout remonter.
 *
 *  - Baisse de prix d'au moins 10 % : l'annonce repasse en tête (au plus une
 *    fois toutes les 2 semaines, pour éviter les baisses d'un euro chaque jour).
 *  - Rotation : chaque heure, sur chaque île, environ 1 ancienne annonce est
 *    remise en avant pour 4 nouvelles publiées. On prend celles qui attendent
 *    depuis le plus longtemps (chacune son tour), de vendeurs encore actifs.
 *
 * Le classement des listes utilise remonte_at quand il existe, sinon la date de
 * publication : la date affichée sur l'annonce, elle, ne change pas.
 */
class Remontees
{
    /** Baisse minimale (10 %) pour remonter l'annonce. */
    public const BAISSE_MIN = 0.10;

    /** Délai entre deux remontées pour baisse de prix. */
    public const DELAI_PRIX_JOURS = 14;

    /** Une annonce n'est remise en avant qu'après une semaine sans remontée. */
    public const AGE_ROTATION_JOURS = 7;

    /** Une ancienne pour… nouvelles. */
    public const RATIO = 4;

    /** Vendeur venu sur Swap'Îles dans ce délai (sinon il ne répondrait pas). */
    public const ACTIVITE_JOURS = 30;

    /** Nombre d'annonces de la première page de l'accueil. */
    public const PAGE_ACCUEIL = 24;

    /** Clé de classement « plus récentes d'abord ». */
    public static function cle(): string
    {
        return self::actif() ? 'COALESCE(listings.remonte_at, listings.created_at)' : 'listings.created_at';
    }

    /** Trie une requête : les plus récentes (ou remontées) d'abord. */
    public static function trier($query)
    {
        return $query->orderByRaw(self::cle() . ' DESC')->orderByDesc('listings.id');
    }

    /** Colonnes présentes (la migration peut ne pas être passée dans un vieux test). */
    public static function actif(): bool
    {
        static $actif = null;

        return $actif ??= Schema::hasColumn('listings', 'remonte_at');
    }

    /** Remonte l'annonce en tête de liste et note la remontée. */
    public static function remonter(Listing $annonce, string $motif, ?Carbon $quand = null): void
    {
        $quand ??= now();
        $champs = ['remonte_at' => $quand];
        if ($motif === 'prix') {
            $champs['prix_remonte_at'] = $quand;
        }
        $annonce->forceFill($champs)->saveQuietly();

        DB::table('listing_remontees')->insert([
            'listing_id' => $annonce->id,
            'motif' => $motif,
            'vues_avant' => (int) $annonce->views_count,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Après une modification d'annonce : si le prix a baissé d'au moins 10 %,
     * l'annonce repasse en tête. Retourne vrai si elle a été remontée.
     */
    public static function apresBaisseDePrix(Listing $annonce, float $ancienPrix): bool
    {
        if (! self::actif()) {
            return false;
        }

        $nouveau = (float) $annonce->price;

        if ($annonce->status !== 'published' || $ancienPrix <= 0 || $nouveau <= 0) {
            return false;
        }

        if ($nouveau > $ancienPrix * (1 - self::BAISSE_MIN) + 0.0001) {
            return false;
        }

        if ($annonce->prix_remonte_at && Carbon::parse($annonce->prix_remonte_at)->gt(now()->subDays(self::DELAI_PRIX_JOURS))) {
            return false;
        }

        self::remonter($annonce, 'prix');

        return true;
    }

    /**
     * Rotation de l'heure : pour chaque île, environ 1 ancienne annonce pour 4
     * nouvelles publiées dans l'heure. Retourne le nombre d'annonces remontées.
     */
    public static function rotation(?callable $hasard = null): int
    {
        if (! self::actif()) {
            return 0;
        }

        $hasard ??= fn () => mt_rand() / mt_getrandmax();
        $total = 0;

        foreach (TerritoireContext::LABELS as $ile) {
            $nouvelles = Listing::query()->where('status', 'published')->where('territoire', $ile)
                ->where('created_at', '>=', now()->subHour())->count();

            // 6 nouvelles → 1 sûre + 50 % de chance d'une 2e : le rapport reste de 1 pour 4.
            $nombre = intdiv($nouvelles, self::RATIO) + ($hasard() < ($nouvelles % self::RATIO) / self::RATIO ? 1 : 0);
            if ($nombre === 0) {
                continue;
            }

            $limite = now()->subDays(self::AGE_ROTATION_JOURS);
            $candidates = Listing::query()
                ->where('status', 'published')->where('territoire', $ile)
                ->where('created_at', '<', $limite)
                ->where(fn ($q) => $q->whereNull('remonte_at')->orWhere('remonte_at', '<', $limite))
                ->whereHas('images')
                ->when(Schema::hasTable('presences'), fn ($q) => $q->whereExists(fn ($p) => $p->select(DB::raw(1))
                    ->from('presences')->whereColumn('presences.user_id', 'listings.user_id')
                    ->where('presences.last_seen_at', '>=', now()->subDays(self::ACTIVITE_JOURS))))
                // Chacune son tour : celles qui attendent depuis le plus longtemps,
                // tirées au sort parmi les 30 premières pour varier.
                ->orderByRaw(self::cle() . ' ASC')
                ->limit(30)
                ->get();

            foreach ($candidates->shuffle()->take($nombre) as $annonce) {
                // Étalées sur l'heure écoulée : mêlées aux nouvelles, pas toutes collées en tête.
                self::remonter($annonce, 'rotation', now()->subMinutes(random_int(0, 59)));
                $total++;
            }
        }

        return $total;
    }

    /**
     * Temps qu'une nouvelle annonce reste en première page de l'accueil, par
     * île : ancienneté de la dernière annonce de la page. Moins d'un jour =
     * la place en tête devient rare (un « Booster » payant aurait du sens).
     *
     * @return array<string,array{heures:?float,nouvelles_7j:int,annonces:int}>
     */
    public static function tempsPremierePage(): array
    {
        $resultat = [];
        $colonne = self::actif() ? 'COALESCE(remonte_at, created_at)' : 'created_at';

        foreach (TerritoireContext::LABELS as $ile) {
            // Même règle que l'accueil : achetables depuis l'île d'abord.
            $requete = Listing::query()->where('status', 'published')
                ->where(fn ($q) => $q->where('territoire', $ile)
                    ->orWhere('also_territoires', 'like', '%"' . $ile . '"%')
                    ->orWhere(fn ($q2) => $q2->where('requires_online_payment', true)->where('allows_colissimo', true)));

            $annonces = (clone $requete)->count();
            $derniere = (clone $requete)->selectRaw($colonne . ' as cle_tri')
                ->orderByRaw($colonne . ' DESC')->offset(self::PAGE_ACCUEIL - 1)->limit(1)->value('cle_tri');

            $resultat[$ile] = [
                'heures' => $derniere ? round(Carbon::parse($derniere)->diffInMinutes(now()) / 60, 1) : null,
                'nouvelles_7j' => Listing::query()->where('status', 'published')->where('territoire', $ile)
                    ->where('created_at', '>=', now()->subDays(7))->count(),
                'annonces' => $annonces,
            ];
        }

        return $resultat;
    }

    /**
     * Bilan des 30 derniers jours pour l'admin : remontées, vues gagnées,
     * ventes après remontée, conseils de prix envoyés et suivis.
     */
    public static function bilan(): array
    {
        $depuis = now()->subDays(30);
        $motifs = [];

        foreach (['prix', 'rotation'] as $motif) {
            $lignes = DB::table('listing_remontees as r')
                ->join('listings as l', 'l.id', '=', 'r.listing_id')
                ->where('r.motif', $motif)->where('r.created_at', '>=', $depuis)
                ->get(['r.listing_id', 'r.vues_avant', 'r.created_at', 'l.views_count', 'l.status', 'l.updated_at']);

            $nombre = $lignes->count();
            $vendues = $lignes->filter(fn ($r) => $r->status === 'sold' && $r->updated_at >= $r->created_at)
                ->unique('listing_id')->count();
            $annonces = $lignes->unique('listing_id')->count();

            $motifs[$motif] = [
                'nombre' => $nombre,
                'annonces' => $annonces,
                // Vues depuis la remontée (en moyenne), à partir de la dernière remontée de chaque annonce.
                'vues_gagnees' => $annonces > 0 ? (int) round($lignes->sortByDesc('created_at')->unique('listing_id')
                    ->avg(fn ($r) => max(0, (int) $r->views_count - (int) $r->vues_avant))) : 0,
                'vendues' => $vendues,
                'taux' => $annonces > 0 ? (int) round(100 * $vendues / $annonces) : null,
            ];
        }

        $conseils = self::actif() ? Listing::query()->where('conseil_prix_at', '>=', $depuis) : null;

        return [
            'motifs' => $motifs,
            'conseils' => [
                'envoyes' => $conseils ? (clone $conseils)->count() : 0,
                'prix_baisse' => $conseils ? (clone $conseils)->whereColumn('prix_remonte_at', '>', 'conseil_prix_at')->count() : 0,
                'vendues' => $conseils ? (clone $conseils)->where('status', 'sold')->whereColumn('updated_at', '>', 'conseil_prix_at')->count() : 0,
            ],
        ];
    }
}

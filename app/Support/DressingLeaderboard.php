<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\Message;
use App\Models\Review;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Classement des « meilleurs dressings » (profils vendeurs), orienté
 * engagement : vues, favoris et messages pèsent plus que les ventes.
 *
 * Le score de chaque vendeur est calculé à partir de quelques requêtes
 * d'agrégat (une par signal), fusionnées en mémoire — léger même avec
 * beaucoup de comptes.
 */
class DressingLeaderboard
{
    /**
     * Tous les dressings classés par score décroissant (score > 0 uniquement).
     *
     * @return Collection<int,object> chaque élément : {user, rank, score,
     *   views, favorites, messages, sales, reviews, listings}
     */
    /**
     * Annonces qui font l'historique d'un dressing : en ligne, vendues ou en
     * pause. Mêmes règles sur le profil : une vente ne fait plus disparaître
     * les vues et favoris de l'article vendu.
     */
    public const STATUTS_COMPTES = ['published', 'sold', 'vacances'];

    /**
     * Nombre d'articles vendus par vendeur : chaque annonce compte une fois,
     * qu'elle ait une vente enregistrée (payée en ligne, main propre, échange,
     * don, même si l'annonce a été supprimée depuis) ou qu'elle ait été close
     * avec l'ancien bouton « Vendue » (avant le 12/10/2026, sans vente
     * enregistrée). Une vente contestée par l'acheteur ne compte pas.
     *
     * @param  array<int,int>  $vendeurs
     * @return \Illuminate\Support\Collection<int,int> nombre par vendeur
     */
    public static function ventes(array $vendeurs): Collection
    {
        if ($vendeurs === []) {
            return collect();
        }

        $parVente = Transaction::query()
            ->whereIn('seller_id', $vendeurs)
            ->whereIn('status', ['paid', 'completed'])
            ->whereNull('acheteur_conteste_at')
            ->whereNotNull('listing_id')
            ->select('seller_id as uid', 'listing_id as lid')
            ->toBase();

        $closes = Listing::query()
            ->whereIn('user_id', $vendeurs)
            ->where('status', 'sold')
            ->select('user_id as uid', 'id as lid')
            ->toBase();

        // UNION (sans ALL) : une annonce vendue ET enregistrée ne compte qu'une fois.
        return DB::query()->fromSub($parVente->union($closes), 'v')
            ->groupBy('uid')
            ->selectRaw('uid, COUNT(*) as c')
            ->pluck('c', 'uid')
            ->map(fn ($c) => (int) $c);
    }

    public static function ranked(): Collection
    {
        $points = config('leaderboard.points');

        // Vendeurs = utilisateurs ayant au moins une annonce, avec la somme des
        // vues et le nombre d'annonces.
        $listingAgg = Listing::query()
            ->whereIn('status', self::STATUTS_COMPTES)
            ->groupBy('user_id')
            ->selectRaw('user_id, COALESCE(SUM(views_count), 0) as views, COUNT(*) as listings')
            ->get()
            ->keyBy('user_id');

        if ($listingAgg->isEmpty()) {
            return collect();
        }

        $sellerIds = $listingAgg->keys()->all();

        // Favoris reçus (sur les annonces du vendeur).
        $favorites = DB::table('favorites')
            ->join('listings', 'favorites.listing_id', '=', 'listings.id')
            ->whereIn('listings.user_id', $sellerIds)
            ->whereIn('listings.status', self::STATUTS_COMPTES)
            ->whereNull('listings.deleted_at')
            // Ses propres annonces mises en favori ne comptent pas.
            ->whereColumn('favorites.user_id', '!=', 'listings.user_id')
            ->groupBy('listings.user_id')
            ->selectRaw('listings.user_id as uid, COUNT(*) as c')
            ->pluck('c', 'uid');

        // Messages reçus (conversations initiées avec le vendeur).
        $messages = Message::query()
            ->whereIn('receiver_id', $sellerIds)
            ->groupBy('receiver_id')
            ->selectRaw('receiver_id as uid, COUNT(*) as c')
            ->pluck('c', 'uid');

        // Ventes : même calcul que le « X vendues » du profil.
        $sales = self::ventes($sellerIds);

        // Avis reçus.
        $reviews = Review::query()
            ->whereIn('reviewed_id', $sellerIds)
            ->groupBy('reviewed_id')
            ->selectRaw('reviewed_id as uid, COUNT(*) as c')
            ->pluck('c', 'uid');

        $rows = collect($sellerIds)->map(function ($uid) use ($listingAgg, $favorites, $messages, $sales, $reviews, $points) {
            $views = (int) ($listingAgg[$uid]->views ?? 0);
            $fav = (int) ($favorites[$uid] ?? 0);
            $msg = (int) ($messages[$uid] ?? 0);
            $sal = (int) ($sales[$uid] ?? 0);
            $rev = (int) ($reviews[$uid] ?? 0);

            $score = $views * $points['view']
                + $fav * $points['favorite']
                + $msg * $points['message']
                + $sal * $points['sale']
                + $rev * $points['review'];

            return (object) [
                'user_id' => (int) $uid,
                'views' => $views,
                'favorites' => $fav,
                'messages' => $msg,
                'sales' => $sal,
                'reviews' => $rev,
                'listings' => (int) ($listingAgg[$uid]->listings ?? 0),
                'score' => (float) $score,
            ];
        })
            ->filter(fn ($r) => $r->score > 0)
            ->sortByDesc('score')
            ->values();

        // Rattache les utilisateurs (non bannis) et numérote le rang.
        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->all() ?: [0])
            ->where('is_banned', false)
            ->get()
            ->keyBy('id');

        return $rows
            ->filter(fn ($r) => $users->has($r->user_id))
            ->values()
            ->map(function ($r, $i) use ($users) {
                $r->user = $users[$r->user_id];
                $r->rank = $i + 1;

                return $r;
            });
    }

    /** Les N meilleurs dressings. */
    public static function top(?int $limit = null): Collection
    {
        $limit = $limit ?: (int) config('leaderboard.top', 10);

        return self::ranked()->take($limit);
    }

    /** Rang (1-based) d'un vendeur dans le classement complet, ou null. */
    public static function rankOf(int $userId): ?int
    {
        $row = self::ranked()->firstWhere('user_id', $userId);

        return $row?->rank;
    }
}

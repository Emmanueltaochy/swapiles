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
    public static function ranked(): Collection
    {
        $points = config('leaderboard.points');

        // Vendeurs = utilisateurs ayant au moins une annonce, avec la somme des
        // vues et le nombre d'annonces.
        $listingAgg = Listing::query()
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
            ->groupBy('listings.user_id')
            ->selectRaw('listings.user_id as uid, COUNT(*) as c')
            ->pluck('c', 'uid');

        // Messages reçus (conversations initiées avec le vendeur).
        $messages = Message::query()
            ->whereIn('receiver_id', $sellerIds)
            ->groupBy('receiver_id')
            ->selectRaw('receiver_id as uid, COUNT(*) as c')
            ->pluck('c', 'uid');

        // Ventes (transactions payées ou terminées).
        $sales = Transaction::query()
            ->whereIn('seller_id', $sellerIds)
            ->whereIn('status', ['paid', 'completed'])
            ->groupBy('seller_id')
            ->selectRaw('seller_id as uid, COUNT(*) as c')
            ->pluck('c', 'uid');

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

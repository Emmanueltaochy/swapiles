<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * « Stories » automatiques de l'accueil : un rond par dressing qui a publié
 * des articles ces derniers jours. Aucun effort pour le vendeur : ses
 * nouvelles annonces SONT sa story.
 *
 * Ordre : les dressings suivis d'abord, puis d'autres vendeurs actifs de
 * l'île pour que la barre ne soit jamais vide. Rond vert = nouveautés pas
 * encore vues, gris = déjà vues.
 */
class Stories
{
    public const JOURS = 7;

    public const MAX_RONDS = 15;

    public const MAX_ARTICLES = 10;

    public static function depuis(): Carbon
    {
        return now()->subDays(self::JOURS);
    }

    /**
     * @return Collection<int, array{vendeur: User, dernier: Carbon, nombre: int, vu: bool, suivi: bool}>
     */
    public static function pour(?User $moi, string $territoire): Collection
    {
        $suivis = $moi ? $moi->followedSellers()->pluck('users.id')->all() : [];
        $exclus = $moi ? array_merge(
            [$moi->id],
            $moi->blockedUsers()->pluck('users.id')->all(),
            $moi->blockedByUsers()->pluck('users.id')->all(),
        ) : [];

        $activite = Listing::query()
            ->where('status', 'published')
            ->where('created_at', '>=', self::depuis())
            ->whereNotIn('user_id', $exclus ?: [0])
            ->where(function ($query) use ($suivis, $territoire) {
                // Un dressing suivi apparaît où qu'il soit ; les autres, sur l'île choisie.
                $query->where('territoire', $territoire)
                    ->when($suivis, fn ($query) => $query->orWhereIn('user_id', $suivis));
            })
            ->selectRaw('user_id, MAX(created_at) as dernier, COUNT(*) as nombre')
            ->groupBy('user_id')
            ->get();

        if ($activite->isEmpty()) {
            return collect();
        }

        $vendeurs = User::query()
            ->whereIn('id', $activite->pluck('user_id'))
            ->where(fn ($query) => $query->whereNull('is_banned')->orWhere('is_banned', false))
            ->get(['id', 'name', 'avatar'])
            ->keyBy('id');

        $vus = $moi
            ? DB::table('story_vues')->where('user_id', $moi->id)->pluck('vu_jusqu_a', 'vendeur_id')
            : collect();

        return $activite
            ->filter(fn ($ligne) => $vendeurs->has($ligne->user_id))
            ->map(function ($ligne) use ($vendeurs, $vus, $suivis) {
                $dernier = Carbon::parse($ligne->dernier);
                $vuJusqua = $vus->get($ligne->user_id);

                return [
                    'vendeur' => $vendeurs[$ligne->user_id],
                    'dernier' => $dernier,
                    'nombre' => (int) $ligne->nombre,
                    'vu' => $vuJusqua !== null && Carbon::parse($vuJusqua)->gte($dernier),
                    'suivi' => in_array($ligne->user_id, $suivis, true),
                ];
            })
            // Pas vus d'abord, puis suivis, puis les plus récents.
            ->sortBy(fn ($s) => [$s['vu'] ? 1 : 0, $s['suivi'] ? 0 : 1, -$s['dernier']->timestamp])
            ->take(self::MAX_RONDS)
            ->values();
    }

    /** Les nouveaux articles d'un dressing, du plus récent au plus ancien. */
    public static function articles(User $vendeur): Collection
    {
        return Listing::query()
            ->with('images')
            ->where('user_id', $vendeur->id)
            ->where('status', 'published')
            ->where('created_at', '>=', self::depuis())
            ->latest()
            ->take(self::MAX_ARTICLES)
            ->get();
    }

    /** Le membre a regardé les nouveautés de ce dressing jusqu'à la plus récente. */
    public static function marquerVu(User $moi, User $vendeur): void
    {
        $dernier = Listing::query()
            ->where('user_id', $vendeur->id)
            ->where('status', 'published')
            ->max('created_at');

        if (! $dernier) {
            return;
        }

        DB::table('story_vues')->upsert(
            [['user_id' => $moi->id, 'vendeur_id' => $vendeur->id, 'vu_jusqu_a' => $dernier]],
            ['user_id', 'vendeur_id'],
            ['vu_jusqu_a']
        );
    }
}

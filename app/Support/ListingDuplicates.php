<?php

namespace App\Support;

use App\Models\Listing;
use Illuminate\Support\Collection;

/**
 * Détection des annonces publiées en double.
 *
 * Cause d'origine : le formulaire de dépôt n'avait aucune protection contre le
 * double envoi. Sur mobile, l'envoi des photos prend plusieurs secondes sans
 * retour visuel : beaucoup de vendeurs ont appuyé deux ou trois fois sur
 * « Publier », créant autant d'annonces identiques.
 *
 * Un groupe = même vendeur + même titre + même prix. On garde toujours la plus
 * ancienne (celle qui porte les vues, les favoris et les messages) et on
 * propose la suppression des copies.
 */
class ListingDuplicates
{
    /**
     * Groupes de doublons, du plus gros au plus petit.
     *
     * @return Collection<int, array{keep: Listing, copies: Collection<int, Listing>}>
     */
    public static function groups(int $limit = 100): Collection
    {
        // On ne considère que les annonces encore visibles ou en brouillon :
        // une annonce vendue porte un historique qu'on ne touche pas.
        $candidates = Listing::query()
            ->whereIn('status', ['published', 'draft'])
            ->orderBy('id')
            ->get(['id', 'user_id', 'title', 'price', 'status', 'created_at']);

        return $candidates
            ->groupBy(fn (Listing $l) => $l->user_id . '|' . mb_strtolower(trim((string) $l->title)) . '|' . (int) $l->price)
            ->filter(fn (Collection $groupe) => $groupe->count() > 1)
            ->map(function (Collection $groupe) {
                $ordonne = $groupe->sortBy('id')->values();

                return [
                    'keep' => $ordonne->first(),
                    'copies' => $ordonne->slice(1)->values(),
                ];
            })
            ->sortByDesc(fn (array $g) => $g['copies']->count())
            ->take($limit)
            ->values();
    }

    /** Nombre total d'annonces en trop (toutes copies confondues). */
    public static function extraCount(): int
    {
        return self::groups(PHP_INT_MAX)->sum(fn (array $g) => $g['copies']->count());
    }
}

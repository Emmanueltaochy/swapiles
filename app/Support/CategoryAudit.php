<?php

namespace App\Support;

use App\Models\Listing;

/**
 * État réel du rangement des annonces.
 *
 * Sert à répondre à une question qu'aucun écran ne posait : où sont vraiment
 * les 2 000 annonces publiées, et combien portent une catégorie qui n'existe
 * pas dans l'arbre ?
 */
class CategoryAudit
{
    /**
     * Répartition des annonces publiées par catégorie de niveau 1, telle
     * qu'elle est enregistrée (y compris les valeurs hors arbre).
     *
     * @return list<array{cle: ?string, label: string, connue: bool, total: int}>
     */
    public static function repartition(): array
    {
        $lignes = Listing::query()
            ->where('status', 'published')
            ->selectRaw('LOWER(COALESCE(category_level1, \'\')) as cle, COUNT(*) as total')
            ->groupBy('cle')
            ->orderByDesc('total')
            ->get();

        return $lignes->map(function ($ligne) {
            $cle = $ligne->cle === '' ? null : $ligne->cle;

            return [
                'cle' => $cle,
                'label' => $cle === null
                    ? 'Sans catégorie'
                    : (Categories::label($cle) ?? $cle),
                'connue' => $cle !== null && isset(Categories::ARBRE[$cle]),
                'total' => (int) $ligne->total,
            ];
        })->all();
    }

    /**
     * Annonces mal rangées : catégorie absente de l'arbre, ou sous-catégorie
     * qui n'appartient pas à la catégorie.
     */
    public static function malRangees(int $limite = 500)
    {
        return Listing::query()
            ->where('status', 'published')
            ->orderByDesc('id')
            ->limit($limite * 4)
            ->get()
            ->reject(fn (Listing $l) => CategoryClassifier::dejaRangee($l))
            ->take($limite)
            ->values();
    }

    /**
     * Ce que le rangement automatique ferait, sans rien modifier.
     *
     * @return array{propositions: \Illuminate\Support\Collection, reconnues: int, inconnues: int}
     */
    public static function apercu(int $limite = 300): array
    {
        $candidates = self::malRangees($limite);

        $propositions = collect();
        $inconnues = 0;

        foreach ($candidates as $listing) {
            $place = CategoryClassifier::classer($listing);

            if ($place === null) {
                $inconnues++;

                continue;
            }

            $propositions->push([
                'listing' => $listing,
                'avant' => trim(($listing->category_level1 ?? '—') . ' / ' . ($listing->category_level2 ?? '—')),
                'apres' => Categories::label($place[0]) . ' / ' . Categories::label($place[1]) . ' / ' . Categories::label($place[2]),
                'place' => $place,
            ]);
        }

        return [
            'propositions' => $propositions,
            'reconnues' => $propositions->count(),
            'inconnues' => $inconnues,
        ];
    }

    /**
     * Applique le rangement. Ne touche jamais une annonce déjà bien rangée,
     * ni une annonce qu'aucune règle ne reconnaît.
     *
     * @return int nombre d'annonces déplacées
     */
    public static function ranger(?int $limite = null): int
    {
        $deplacees = 0;

        Listing::query()
            ->where('status', 'published')
            ->orderBy('id')
            ->chunkById(200, function ($lot) use (&$deplacees, $limite) {
                foreach ($lot as $listing) {
                    if ($limite !== null && $deplacees >= $limite) {
                        return false;
                    }

                    if (CategoryClassifier::dejaRangee($listing)) {
                        continue;
                    }

                    $place = CategoryClassifier::classer($listing);

                    if ($place === null) {
                        continue;
                    }

                    // saveQuietly : ranger une annonce n'est pas une
                    // modification du vendeur, personne ne doit être notifié.
                    $listing->category_level1 = $place[0];
                    $listing->category_level2 = $place[1];
                    $listing->category_level3 = $place[2];
                    $listing->saveQuietly();

                    $deplacees++;
                }

                return true;
            });

        return $deplacees;
    }
}

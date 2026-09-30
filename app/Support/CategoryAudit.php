<?php

namespace App\Support;

use App\Models\Listing;
use Illuminate\Support\Collection;

/**
 * État réel du rangement des annonces.
 *
 * Le formulaire de dépôt n'a longtemps offert que Femme / Homme / Enfant : le
 * vendeur d'un réfrigérateur n'avait aucun rayon juste à choisir et prenait
 * celui qui existait. Toutes les annonces portent donc une catégorie « valide »
 * sans que le contenu corresponde. Cet écran sert à le voir et à le corriger.
 */
class CategoryAudit
{
    /**
     * Répartition des annonces publiées par catégorie de niveau 1.
     *
     * @return list<array{cle: ?string, label: string, connue: bool, total: int}>
     */
    public static function repartition(): array
    {
        return Listing::query()
            ->where('status', 'published')
            ->selectRaw("LOWER(COALESCE(category_level1, '')) as cle, COUNT(*) as total")
            ->groupBy('cle')
            ->orderByDesc('total')
            ->get()
            ->map(function ($ligne) {
                $cle = $ligne->cle === '' ? null : $ligne->cle;

                return [
                    'cle' => $cle,
                    'label' => $cle === null ? 'Sans catégorie' : (Categories::label($cle) ?? $cle),
                    'connue' => $cle !== null && isset(Categories::ARBRE[$cle]),
                    'total' => (int) $ligne->total,
                ];
            })
            ->all();
    }

    /**
     * Répartition par sous-catégorie : c'est là que se cache le désordre, tout
     * étant regroupé sous trois catégories seulement.
     *
     * @return list<array{niveau1: string, niveau2: string, label: string, connue: bool, total: int}>
     */
    public static function repartitionNiveau2(): array
    {
        return Listing::query()
            ->where('status', 'published')
            ->selectRaw("LOWER(COALESCE(category_level1, '')) as n1, LOWER(COALESCE(category_level2, '')) as n2, COUNT(*) as total")
            ->groupBy('n1', 'n2')
            ->orderByDesc('total')
            ->get()
            ->map(function ($ligne) {
                $n1 = $ligne->n1 === '' ? '—' : $ligne->n1;
                $n2 = $ligne->n2 === '' ? '—' : $ligne->n2;

                return [
                    'niveau1' => Categories::label($n1) ?? $n1,
                    'niveau2' => $n2,
                    'label' => Categories::label($n2) ?? $n2,
                    'connue' => isset(Categories::ARBRE[$n1]['enfants'][$n2]),
                    'total' => (int) $ligne->total,
                ];
            })
            ->all();
    }

    /**
     * Ce que le rangement ferait, sans rien modifier.
     *
     * @return array{aRanger: Collection, aReclasser: Collection, laissees: int}
     */
    public static function apercu(int $limite = 400): array
    {
        $aRanger = collect();
        $aReclasser = collect();
        $laissees = 0;

        Listing::query()
            ->where('status', 'published')
            ->orderByDesc('id')
            ->chunkById(300, function ($lot) use (&$aRanger, &$aReclasser, &$laissees, $limite) {
                foreach ($lot as $listing) {
                    $decision = CategoryClassifier::decider($listing);

                    if ($decision['action'] === 'laisser' || $decision['place'] === null) {
                        $laissees++;

                        continue;
                    }

                    $ligne = [
                        'listing' => $listing,
                        'avant' => self::placeLisible($listing->category_level1, $listing->category_level2),
                        'apres' => Categories::label($decision['place'][0]) . ' › ' . Categories::label($decision['place'][1]),
                    ];

                    if ($decision['action'] === 'ranger') {
                        $aRanger->push($ligne);
                    } else {
                        $aReclasser->push($ligne);
                    }
                }

                return $aRanger->count() + $aReclasser->count() < $limite;
            }, 'id', 'id');

        return ['aRanger' => $aRanger, 'aReclasser' => $aReclasser, 'laissees' => $laissees];
    }

    /**
     * Les mots les plus fréquents dans les titres qu'aucune règle ne reconnaît.
     *
     * C'est la liste des rayons qui manquent encore : si « vaisselier » ou
     * « paréo » revient cent fois, c'est qu'il faut une règle pour lui.
     *
     * @return list<array{mot: string, total: int}>
     */
    public static function motsNonReconnus(int $combien = 40): array
    {
        $ignores = [
            'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'a', 'au', 'aux',
            'en', 'pour', 'avec', 'sans', 'sur', 'par', 'neuf', 'neuve', 'bon', 'bonne',
            'etat', 'tres', 'taille', 'jamais', 'porte', 'portee', 'lot', 'petit', 'petite',
            'grand', 'grande', 'vends', 'vendu', 'noir', 'noire', 'blanc', 'blanche', 'bleu',
            'rouge', 'vert', 'rose', 'gris', 'jaune', 'beige', 'marron', 'violet', 'orange',
        ];

        $comptes = [];

        Listing::query()
            ->where('status', 'published')
            ->select('id', 'title', 'description', 'category_level1', 'category_level2')
            ->chunkById(500, function ($lot) use (&$comptes, $ignores) {
                foreach ($lot as $listing) {
                    if (CategoryClassifier::classerDepuisTitre($listing) !== null) {
                        continue;
                    }

                    foreach (self::mots($listing->title) as $mot) {
                        if (mb_strlen($mot) < 4 || in_array($mot, $ignores, true)) {
                            continue;
                        }

                        $comptes[$mot] = ($comptes[$mot] ?? 0) + 1;
                    }
                }
            });

        arsort($comptes);

        return collect($comptes)->take($combien)
            ->map(fn ($total, $mot) => ['mot' => $mot, 'total' => $total])
            ->values()->all();
    }

    /**
     * Applique le rangement.
     *
     * Chaque annonce déplacée garde en mémoire la catégorie qu'elle portait :
     * l'opération touche des milliers de lignes, elle doit pouvoir se défaire.
     *
     * @return array{rangees: int, reclassees: int}
     */
    public static function ranger(bool $inclureReclassements = true): array
    {
        $rangees = 0;
        $reclassees = 0;

        Listing::query()
            ->where('status', 'published')
            ->orderBy('id')
            ->chunkById(200, function ($lot) use (&$rangees, &$reclassees, $inclureReclassements) {
                foreach ($lot as $listing) {
                    $decision = CategoryClassifier::decider($listing);

                    if ($decision['place'] === null) {
                        continue;
                    }

                    if ($decision['action'] === 'reclasser' && ! $inclureReclassements) {
                        continue;
                    }

                    // On ne memorise que la premiere place connue : deux
                    // rangements de suite ne doivent pas effacer l'originale.
                    if ($listing->category_avant === null) {
                        $listing->category_avant = [
                            'level1' => $listing->category_level1,
                            'level2' => $listing->category_level2,
                            'level3' => $listing->category_level3,
                        ];
                    }

                    $listing->category_level1 = $decision['place'][0];
                    $listing->category_level2 = $decision['place'][1];
                    $listing->category_level3 = $decision['place'][2];

                    // saveQuietly : ranger n'est pas une modification du
                    // vendeur, personne ne doit etre notifie.
                    $listing->saveQuietly();

                    $decision['action'] === 'ranger' ? $rangees++ : $reclassees++;
                }
            });

        return ['rangees' => $rangees, 'reclassees' => $reclassees];
    }

    /** Nombre d'annonces qu'un rangement automatique a déplacées. */
    public static function nombreDeplacees(): int
    {
        return Listing::query()->whereNotNull('category_avant')->count();
    }

    /**
     * Remet chaque annonce déplacée là où elle était.
     *
     * @return int nombre d'annonces remises en place
     */
    public static function annuler(): int
    {
        $remises = 0;

        Listing::query()
            ->whereNotNull('category_avant')
            ->orderBy('id')
            ->chunkById(200, function ($lot) use (&$remises) {
                foreach ($lot as $listing) {
                    $avant = $listing->category_avant;

                    $listing->category_level1 = $avant['level1'] ?? null;
                    $listing->category_level2 = $avant['level2'] ?? null;
                    $listing->category_level3 = $avant['level3'] ?? null;
                    $listing->category_avant = null;
                    $listing->saveQuietly();

                    $remises++;
                }
            });

        return $remises;
    }

    private static function placeLisible(?string $n1, ?string $n2): string
    {
        $n1 = filled($n1) ? (Categories::label($n1) ?? $n1) : '—';
        $n2 = filled($n2) ? (Categories::label($n2) ?? $n2) : '—';

        return $n1 . ' › ' . $n2;
    }

    /** @return list<string> */
    private static function mots(?string $texte): array
    {
        $texte = mb_strtolower(trim((string) $texte));
        $texte = strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y', 'ñ' => 'n',
        ]);
        $texte = preg_replace('/[^a-z0-9]+/u', ' ', $texte);

        return array_values(array_filter(explode(' ', trim($texte))));
    }
}

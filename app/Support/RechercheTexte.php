<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * Recherche de texte, mot par mot.
 *
 * Avant, « jardin plante » cherchait cette phrase exacte : une annonce
 * « Plante verte » rangée dans Jardin ne sortait pas. Maintenant chaque mot
 * doit se retrouver quelque part (titre, description, marque, catégories),
 * dans n'importe quel ordre, au singulier comme au pluriel.
 */
class RechercheTexte
{
    public const CHAMPS = ['title', 'description', 'marque', 'category_level1', 'category_level2', 'category_level3'];

    private const MOTS_VIDES = [
        'a', 'au', 'aux', 'avec', 'de', 'des', 'du', 'd', 'en', 'et', 'l', 'la', 'le', 'les',
        'pour', 'sur', 'un', 'une', 'ou', 'par', 'mon', 'ma', 'mes',
    ];

    /** Les mots utiles de la recherche (sans « de », « la »…, pluriel ramené au singulier). */
    public static function mots(string $texte): array
    {
        $morceaux = preg_split('/[\s,;:\/\'’"+()!?.]+/u', mb_strtolower(trim($texte)), -1, PREG_SPLIT_NO_EMPTY);

        $mots = [];
        foreach ($morceaux as $mot) {
            if (in_array($mot, self::MOTS_VIDES, true) || mb_strlen($mot) < 2) {
                continue;
            }
            // « plantes » → « plante », « jeux » → « jeu » : le LIKE retrouve les deux formes.
            if (mb_strlen($mot) > 3 && in_array(mb_substr($mot, -1), ['s', 'x'], true)) {
                $mot = mb_substr($mot, 0, -1);
            }
            $mots[] = $mot;
        }

        return array_values(array_unique($mots));
    }

    /** Ajoute la recherche à une requête d'annonces. */
    public static function appliquer($query, string $texte, array $champs = self::CHAMPS)
    {
        $mots = self::mots($texte) ?: [trim($texte)];

        foreach ($mots as $mot) {
            $query->where(function ($q) use ($mot, $champs) {
                foreach ($champs as $champ) {
                    $q->orWhere($champ, 'like', '%' . $mot . '%');
                }
            });
        }

        return $query;
    }
}

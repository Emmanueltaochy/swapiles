<?php

namespace App\Support;

/**
 * Normalisation de l'état des articles.
 *
 * Les annonces déposées stockent « Très bon état », mais celles importées
 * (migration) stockent parfois « Tres-bon-etat » (slug). La correspondance
 * exacte du filtre ratait donc une partie des annonces. Cette classe ramène
 * toutes les variantes à une même clé et à un même libellé propre.
 */
class Etat
{
    /** Libellés canoniques par clé normalisée. */
    private const CANONICAL = [
        'neuf avec etiquette' => 'Neuf avec étiquette',
        'neuf sans etiquette' => 'Neuf sans étiquette',
        'tres bon etat' => 'Très bon état',
        'bon etat' => 'Bon état',
        'satisfaisant' => 'Satisfaisant',
    ];

    /** Clé normalisée : minuscules, sans accent, séparateurs unifiés. */
    public static function normalize(?string $value): string
    {
        $s = mb_strtolower(trim((string) $value));

        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'û' => 'u', 'ù' => 'u', 'ü' => 'u', 'ç' => 'c',
        ]);

        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim($s);
    }

    /** Libellé propre à afficher (ex. « Tres-bon-etat » -> « Très bon état »). */
    public static function label(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $key = self::normalize($value);

        return self::CANONICAL[$key] ?? ucfirst($key);
    }

    /** Liste des états canoniques (clé stockée => libellé) pour les filtres. */
    public static function options(): array
    {
        return array_values(self::CANONICAL);
    }

    /**
     * « Étiquette » ne veut rien dire pour un frigo ou un livre. Pour les
     * objets, les deux premiers états se disent autrement — mais la VALEUR
     * stockée reste la même : le filtre « Neuf » de la recherche continue de
     * trouver les vêtements comme les objets.
     */
    private const LIBELLES_OBJET = [
        'neuf avec etiquette' => 'Neuf, sous emballage',
        'neuf sans etiquette' => 'Neuf, déballé',
    ];

    /**
     * Libellé adapté au rayon de l'annonce.
     *
     * @param  string|null  $niveau1  catégorie de l'annonce (null : libellé textile)
     */
    public static function libelle(?string $value, ?string $niveau1 = null, ?string $niveau2 = null): ?string
    {
        if (blank($value)) {
            return null;
        }

        if ($niveau1 !== null && Categories::fiche($niveau1, $niveau2)['etat'] === 'objet') {
            $objet = self::LIBELLES_OBJET[self::normalize($value)] ?? null;
            if ($objet) {
                return $objet;
            }
        }

        return self::label($value);
    }

    /**
     * Les choix du formulaire : valeur stockée => libellé, pour les deux types
     * d'articles. Le formulaire bascule de l'un à l'autre selon le rayon.
     *
     * @return array{textile: array<string, string>, objet: array<string, string>}
     */
    public static function choixFormulaire(): array
    {
        $textile = [];
        $objet = [];

        foreach (self::CANONICAL as $cle => $valeur) {
            $textile[$valeur] = $valeur;
            $objet[$valeur] = self::LIBELLES_OBJET[$cle] ?? $valeur;
        }

        return ['textile' => $textile, 'objet' => $objet];
    }
}

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
}

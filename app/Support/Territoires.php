<?php

namespace App\Support;

/**
 * Aide à l'affichage des territoires.
 *
 * La valeur STOCKÉE en base (colonne territoire) reste inchangée
 * (« Martinique », « Guadeloupe »…) pour ne pas casser le filtrage des
 * annonces. Cette classe ne gère que le NOM AFFICHÉ, avec l'article « La »
 * devant, sauf Mayotte.
 */
class Territoires
{
    /** Nom affiché (avec « La » sauf Mayotte). */
    public static function display(?string $label): string
    {
        return match ($label) {
            'Martinique' => 'La Martinique',
            'Guadeloupe' => 'La Guadeloupe',
            'Guyane' => 'La Guyane',
            default => (string) $label, // « La Réunion » et « Mayotte » restent tels quels
        };
    }

    /**
     * Forme LOCATIVE (« je suis … », « livrer … ») avec la bonne préposition :
     * « à la Réunion », « en Guadeloupe », « en Martinique », « en Guyane », « à Mayotte ».
     */
    public static function locative(?string $label): string
    {
        return match ($label) {
            'La Réunion' => 'à la Réunion',
            'Martinique' => 'en Martinique',
            'Guadeloupe' => 'en Guadeloupe',
            'Guyane' => 'en Guyane',
            'Mayotte' => 'à Mayotte',
            default => $label ? 'à ' . $label : 'sur une autre île',
        };
    }

    /**
     * Forme d'ORIGINE (« un acheteur … ») :
     * « de la Réunion », « de Guadeloupe », « de Martinique », « de Guyane », « de Mayotte ».
     */
    public static function origin(?string $label): string
    {
        return match ($label) {
            'La Réunion' => 'de la Réunion',
            'Martinique' => 'de Martinique',
            'Guadeloupe' => 'de Guadeloupe',
            'Guyane' => 'de Guyane',
            'Mayotte' => 'de Mayotte',
            default => $label ? 'de ' . $label : 'd’une autre île',
        };
    }
}

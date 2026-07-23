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
}

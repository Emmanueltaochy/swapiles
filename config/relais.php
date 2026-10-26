<?php

/*
|--------------------------------------------------------------------------
| Charte des points relais Swap'Îles
|--------------------------------------------------------------------------
| Règles acceptées par chaque commerçant (page /points-relais/charte).
| Changer une règle de fond => changer « charte_version » : les commerçants
| devront accepter la nouvelle version à leur prochaine visite.
| La commission par colis est dans config/pricing.php (relay_merchant_fee).
*/

return [
    'charte_version' => '2026-10',

    // L'acheteur a ce nombre de jours pour retirer son colis, ensuite retour au vendeur.
    'delai_garde_jours' => (int) env('RELAIS_DELAI_GARDE_JOURS', 10),

    // Taille des colis acceptés (un petit commerce n'est pas un entrepôt).
    'poids_max_kg' => (float) env('RELAIS_POIDS_MAX_KG', 15),
    'longueur_max_cm' => (int) env('RELAIS_LONGUEUR_MAX_CM', 100),

    // Versement mensuel par virement, à partir de ce solde.
    'versement_minimum' => (float) env('RELAIS_VERSEMENT_MINIMUM', 10),

    // Arrêt sans frais, avec ce préavis pour vider les colis en stock.
    'preavis_jours' => 15,

    // TVA des DOM (taux normal). Guyane et Mayotte : TVA non applicable.
    'taux_tva' => ['La Réunion' => 8.5, 'Martinique' => 8.5, 'Guadeloupe' => 8.5],
];

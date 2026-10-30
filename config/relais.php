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
    'charte_version' => '2026-10-b',

    // L'acheteur a ce nombre de jours pour retirer son colis, ensuite retour au vendeur.
    'delai_garde_jours' => (int) env('RELAIS_DELAI_GARDE_JOURS', 10),

    // Taille des colis acceptés (un petit commerce n'est pas un entrepôt).
    'poids_max_kg' => (float) env('RELAIS_POIDS_MAX_KG', 15),
    'longueur_max_cm' => (int) env('RELAIS_LONGUEUR_MAX_CM', 100),

    // Versement mensuel par virement, à partir de ce solde.
    'versement_minimum' => (float) env('RELAIS_VERSEMENT_MINIMUM', 10),

    // Arrêt sans frais, avec ce préavis pour vider les colis en stock.
    'preavis_jours' => 15,

    // Colis perdu ou remis sans code par la faute du commerçant : sa valeur
    // peut lui être retenue sur ses commissions, dans cette limite par colis.
    'plafond_responsabilite' => (float) env('RELAIS_PLAFOND_RESPONSABILITE', 100),

    // Vol avec effraction, sinistre : le commerçant envoie la preuve (plainte,
    // déclaration à l'assurance) dans ce délai, sinon la perte est sa faute.
    'delai_justificatif_jours' => 7,

    // Prospection (mails aux commerces repérés) : envois par jour au plus.
    // La boîte contact@ est plafonnée à 1 000 e-mails/jour pour tout le site.
    'prospection_par_jour' => (int) env('RELAIS_PROSPECTION_PAR_JOUR', 40),

    // TVA des DOM (taux normal). Guyane et Mayotte : TVA non applicable.
    'taux_tva' => ['La Réunion' => 8.5, 'Martinique' => 8.5, 'Guadeloupe' => 8.5],
];

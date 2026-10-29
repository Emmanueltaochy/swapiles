<?php

/*
|--------------------------------------------------------------------------
| Valeur de Swap'Îles (page admin « Valeur de Swap'Îles »)
|--------------------------------------------------------------------------
| Hypothèses PRUDENTES de l'estimation. Ce ne sont pas des prix garantis :
| seule une offre réelle (ou un expert) fixe un prix. À ajuster au besoin.
*/

return [
    // Valeur plancher de la technologie (site, appli iOS/Android, paiement,
    // relais, admin) : ce qu'un acheteur accepte de payer pour ne pas la
    // refaire. Bien en dessous du coût de développement : un repreneur décote.
    'technologie' => [15000, 40000],

    // Ce que vaut un membre actif (venu ces 30 derniers jours) pour un
    // repreneur, quand il n'y a pas encore de revenus réguliers.
    'par_membre_actif' => [1, 3],

    // Multiples du rythme annuel de revenus, selon la croissance des revenus
    // (3 derniers mois complets comparés aux 3 précédents). Une place de
    // marché pas encore rentable se vend plutôt dans le bas de ces fourchettes.
    'multiples' => [
        ['croissance_min' => 75, 'bas' => 2.0, 'haut' => 4.0, 'libelle' => 'forte croissance (plus de 75 % en 3 mois)'],
        ['croissance_min' => 25, 'bas' => 1.5, 'haut' => 3.0, 'libelle' => 'bonne croissance (25 à 75 % en 3 mois)'],
        ['croissance_min' => 0, 'bas' => 1.0, 'haut' => 2.0, 'libelle' => 'croissance faible (0 à 25 % en 3 mois)'],
        ['croissance_min' => null, 'bas' => 0.5, 'haut' => 1.0, 'libelle' => 'revenus en baisse ou trop récents pour juger'],
    ],
];

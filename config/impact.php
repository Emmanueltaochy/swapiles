<?php

/*
|--------------------------------------------------------------------------
| Impact du réemploi (page admin « Impact réemploi »)
|--------------------------------------------------------------------------
| Estimations MOYENNES par catégorie, volontairement prudentes, à affiner
| avec la Base Empreinte de l'ADEME pour un dossier officiel :
|   poids : poids moyen d'un article (kg), remplacé par le poids saisi par
|           le vendeur quand il existe ;
|   co2   : kg CO2e évités par article, c'est-à-dire la fabrication d'un
|           article neuf qui n'a pas eu lieu.
| Hypothèse : chaque article réemployé évite l'achat d'un article neuf.
*/

return [
    'categories' => [
        'femme' => ['poids' => 0.5, 'co2' => 15],
        'homme' => ['poids' => 0.5, 'co2' => 15],
        'enfant' => ['poids' => 0.4, 'co2' => 8],
        'maison' => ['poids' => 3.0, 'co2' => 30],
        'high-tech' => ['poids' => 1.0, 'co2' => 60],
        'sport-loisirs' => ['poids' => 2.0, 'co2' => 25],
        'beaute-sante' => ['poids' => 0.3, 'co2' => 3],
        'culture-loisirs' => ['poids' => 0.6, 'co2' => 3],
        'auto-moto' => ['poids' => 3.0, 'co2' => 30],
        'jardin-bricolage' => ['poids' => 2.0, 'co2' => 15],
        'animaux' => ['poids' => 1.0, 'co2' => 5],
    ],

    'defaut' => ['poids' => 1.0, 'co2' => 10],

    // Voiture thermique moyenne, kg CO2e par km (ordre de grandeur ADEME).
    'co2_par_km_voiture' => 0.2,
];

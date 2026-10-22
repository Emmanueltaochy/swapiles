<?php

/*
|--------------------------------------------------------------------------
| Dossier partenaires (PDF généré depuis l'admin)
|--------------------------------------------------------------------------
| Les formules proposées aux entreprises. « prix » est affiché tel quel :
| tant qu'aucun tarif n'est décidé, on écrit « Sur devis ».
*/

return [
    'contact' => [
        'nom' => env('PARTENAIRES_CONTACT_NOM', 'Emmanuel Taochy'),
        'fonction' => 'Fondateur',
        'email' => env('PARTENAIRES_CONTACT_EMAIL', 'contact@swapiles.com'),
        'telephone' => env('PARTENAIRES_CONTACT_TELEPHONE'),
    ],

    'formules' => [
        [
            'nom' => 'Point relais',
            'prix' => 'Gratuit',
            'pour' => 'Commerces et enseignes de proximité',
            'inclus' => [
                'Vos clients déposent et retirent leurs colis Swap’Îles chez vous',
                'Du passage en magasin : chaque colis amène deux visites',
                'Badge « Partenaire » et logo sur la page partenaires',
                'Rapport d’impact mensuel de votre point relais',
            ],
        ],
        [
            'nom' => 'Partenaire île',
            'prix' => 'Sur devis',
            'pour' => 'Entreprises qui veulent agir sur une île',
            'inclus' => [
                'Une journée réemploi Swap’Îles dans vos locaux ou un magasin',
                'Mention « Partenaire du réemploi local » sur Swap’Îles',
                'Rapport d’impact trimestriel de l’île, prêt pour votre rapport RSE',
                'Une publication commune sur nos réseaux sociaux',
            ],
        ],
        [
            'nom' => 'Partenaire officiel',
            'prix' => 'Sur devis',
            'pour' => 'Groupes présents dans plusieurs territoires',
            'inclus' => [
                'Tout le contenu de la formule « Partenaire île », sur toutes les îles',
                'Plusieurs journées réemploi par an, ouvertes à vos salariés',
                'Logo dans l’appli et sur le site, comme partenaire officiel',
                'Rapport d’impact trimestriel détaillé, avec la méthode de calcul',
            ],
        ],
    ],
];

<?php

/*
|--------------------------------------------------------------------------
| IA dépôt : « Remplir avec l'IA » sur la page de dépôt d'annonce
|--------------------------------------------------------------------------
| Masqué tant que ANTHROPIC_API_KEY n'est pas renseignée (secret GitHub).
| L'IA lit les photos et propose titre, description, catégorie, état,
| marque, taille et prix ; le vendeur relit avant de publier.
*/

return [
    'cle' => env('ANTHROPIC_API_KEY'),

    // Haiku 5.5 : rapide et quasi gratuit (≈ 0,1 centime par annonce).
    'modele' => env('IA_DEPOT_MODELE', 'claude-haiku-5-5'),

    // Garde-fous : par membre et par jour, et plafond mensuel (en dollars, comme la facture Anthropic).
    'limite_par_jour' => (int) env('IA_DEPOT_LIMITE_JOUR', 20),
    'budget_mensuel_usd' => (float) env('IA_DEPOT_BUDGET_MENSUEL', 10),

    // Photos envoyées à l'IA : 4 au plus, réduites (moins de jetons, même résultat).
    'photos_max' => 4,
    'cote_max_px' => 1024,

    // Tarifs publics par million de jetons (entrée, sortie), pour estimer le coût.
    'tarifs' => [
        'claude-haiku-5-5' => [0.10, 0.50],
        'claude-sonnet-5-5' => [2.00, 10.00],
        'claude-opus-5-5' => [4.00, 20.00],
    ],
];

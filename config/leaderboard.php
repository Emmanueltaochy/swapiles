<?php

/*
|--------------------------------------------------------------------------
| Classement des dressings (« Meilleurs dressings »)
|--------------------------------------------------------------------------
| Pondération orientée ENGAGEMENT : la popularité du dressing (vues, favoris,
| messages) pèse plus que les ventes, pour que le classement soit vivant dès
| le lancement, quand il y a encore peu de ventes.
|
| Score = vues×view + favoris×favorite + messages×message + ventes×sale
|         + avis×review
|
| Configurable via .env sans redéploiement (config non cachée en prod).
*/

return [
    'points' => [
        'view' => (float) env('LEADERBOARD_PTS_VIEW', 1),
        'favorite' => (float) env('LEADERBOARD_PTS_FAVORITE', 20),
        'message' => (float) env('LEADERBOARD_PTS_MESSAGE', 10),
        'sale' => (float) env('LEADERBOARD_PTS_SALE', 25),
        'review' => (float) env('LEADERBOARD_PTS_REVIEW', 8),
    ],

    // Nombre de dressings affichés dans le Top public.
    'top' => (int) env('LEADERBOARD_TOP', 10),
];

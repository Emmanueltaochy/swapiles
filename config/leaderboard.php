<?php

/*
|--------------------------------------------------------------------------
| Classement des dressings (« Meilleurs dressings »)
|--------------------------------------------------------------------------
| Pondération orientée QUALITÉ D'ENGAGEMENT : les signaux d'intention réelle
| (favoris, ventes, avis) priment. La vue reste comptée mais FAIBLEMENT : c'est
| de la portée, facilement gonflée par la taille du catalogue, pas un vrai
| signal de qualité. Ainsi un dressing complet (favoris + ventes) passe devant
| un gros catalogue qui ne récolte que des vues.
|
| Score = vues×view + favoris×favorite + messages×message + ventes×sale
|         + avis×review
|
| Configurable via .env sans redéploiement (config non cachée en prod).
*/

return [
    'points' => [
        'view' => (float) env('LEADERBOARD_PTS_VIEW', 0.3),
        'favorite' => (float) env('LEADERBOARD_PTS_FAVORITE', 25),
        'message' => (float) env('LEADERBOARD_PTS_MESSAGE', 8),
        'sale' => (float) env('LEADERBOARD_PTS_SALE', 50),
        'review' => (float) env('LEADERBOARD_PTS_REVIEW', 15),
    ],

    // Nombre de dressings affichés dans le Top public.
    'top' => (int) env('LEADERBOARD_TOP', 10),
];

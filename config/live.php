<?php

/*
| Vente en direct (live shopping).
|
| Tant que LIVE_OUVERT n'est pas à true, le live n'existe que pour les
| administrateurs et les testeurs listés : pour tous les autres, les pages
| répondent « introuvable » et aucun lien n'apparaît.
*/
return [
    'ouvert_a_tous' => (bool) env('LIVE_OUVERT', false),

    // E-mails de testeurs (séparés par des virgules), en plus des admins.
    'testeurs' => array_filter(array_map('trim', explode(',', (string) env('LIVE_TESTEURS', '')))),

    // Minutes pendant lesquelles un article reste réservé à l'acheteur du live.
    'reservation_minutes' => (int) env('LIVE_RESERVATION_MINUTES', 5),

    // Service vidéo (LiveKit Cloud). Clés dans les secrets GitHub, jamais dans le code.
    'livekit' => [
        'url' => env('LIVEKIT_URL'),
        'key' => env('LIVEKIT_API_KEY'),
        'secret' => env('LIVEKIT_API_SECRET'),
    ],

    // Enregistrement des lives (replay) : la vidéo est déposée par LiveKit
    // dans un espace de stockage compatible S3 (Cloudflare R2, Scaleway…).
    // Sans ces réglages, rien n'est enregistré.
    'replay' => [
        'cle' => env('LIVE_S3_KEY'),
        'secret' => env('LIVE_S3_SECRET'),
        'bucket' => env('LIVE_S3_BUCKET'),
        'region' => env('LIVE_S3_REGION', 'auto'),
        'endpoint' => env('LIVE_S3_ENDPOINT'),
        // Adresse publique des fichiers (ex. https://pub-xxxx.r2.dev).
        'url_publique' => env('LIVE_REPLAY_URL'),
    ],

    // Le replay commence quelques secondes après la demande d'enregistrement :
    // décalage appliqué pour que le chat tombe pile au bon moment.
    'replay_decalage_secondes' => (int) env('LIVE_REPLAY_DECALAGE', 3),

    // Rappel « le live commence dans N minutes » aux membres inscrits.
    'rappel_minutes' => 15,

    // Spectateur compté « présent » s'il a donné signe de vie depuis N secondes.
    'presence_secondes' => 30,
];

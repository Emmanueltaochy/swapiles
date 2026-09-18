<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notifications push (Firebase Cloud Messaging — API HTTP v1)
    |--------------------------------------------------------------------------
    |
    | - project_id : l'ID du projet Firebase (visible dans la console Firebase).
    | - credentials_path : chemin du fichier JSON du compte de service Firebase
    |   (déposé sur le serveur au déploiement, hors du dépôt Git).
    |
    | Tant que ces deux valeurs ne sont pas renseignées, l'envoi est simplement
    | ignoré (l'app et l'admin fonctionnent, aucune notification n'est envoyée).
    |
    */

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials_path' => env('FCM_CREDENTIALS_PATH', storage_path('app/firebase/service-account.json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications push iOS (Apple APNs, en direct)
    |--------------------------------------------------------------------------
    |
    | L'application iOS n'embarque pas le SDK Firebase : le jeton qu'elle
    | enregistre est un jeton APNs, que Firebase refuse. Les iPhone/iPad sont
    | donc servis directement par Apple.
    |
    | - key_path : clé d'authentification APNs (.p8) créée dans le compte
    |   développeur Apple, déposée sur le serveur au déploiement (hors dépôt Git).
    | - key_id   : identifiant de cette clé (10 caractères, donné par Apple).
    | - team_id  : identifiant de l'équipe Apple.
    | - bundle_id: identifiant de l'app (doit correspondre exactement).
    | - production : vrai pour un binaire TestFlight / App Store, faux pour un
    |   binaire installé depuis Xcode. En cas d'erreur, l'autre environnement
    |   est tenté automatiquement.
    |
    | Tant que la clé n'est pas installée, l'envoi vers iOS est simplement ignoré.
    |
    */

    'apns' => [
        'key_path' => env('APNS_KEY_PATH', storage_path('app/apple/apns-key.p8')),
        'key_id' => env('APNS_KEY_ID'),
        'team_id' => env('APNS_TEAM_ID'),
        'bundle_id' => env('APNS_BUNDLE_ID', 'com.swapiles.app'),
        'production' => env('APNS_PRODUCTION', true),
    ],

];

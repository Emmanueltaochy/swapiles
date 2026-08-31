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

];

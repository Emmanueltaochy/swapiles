<?php

/*
|--------------------------------------------------------------------------
| Agent directeur : surveille Swap'Îles, prospecte et propose des actions
|--------------------------------------------------------------------------
| Il propose, Emmanuel valide : jamais d'argent, jamais de message aux
| membres, jamais de code mis en ligne sans accord. Les mails de prospection
| partent de no-reply@ (boîte séparée) et les réponses arrivent sur contact@.
*/

return [
    'actif' => (bool) env('AGENT_ACTIF', true),

    // Résumé du matin : modèle plus fin ; tri des réponses : modèle rapide.
    'modele_rapport' => env('AGENT_MODELE_RAPPORT', 'claude-sonnet-5-5'),
    'modele_tri' => env('AGENT_MODELE_TRI', 'claude-haiku-5-5'),
    // « Parler au CEO » (Bureau du CEO, à la voix) : réponses courtes à l'oral.
    'modele_voix' => env('AGENT_MODELE_VOIX', 'claude-sonnet-5-5'),
    'budget_mensuel_usd' => (float) env('AGENT_BUDGET_MENSUEL', 15),

    // Boîte d'envoi de la prospection (secrets GitHub PROSPECTION_MAIL_*).
    'expediteur' => [
        'adresse' => env('PROSPECTION_MAIL_USERNAME'),
        'nom' => 'Emmanuel – Swap’Îles',
        'reponses' => env('PROSPECTION_REPONSES', 'contact@swapiles.com'),
    ],
    // Qui reçoit les alertes (prospect intéressé, résumé du matin), séparés par des virgules.
    // Vide : les adresses admin (ADMIN_EMAILS).
    'alertes' => env('AGENT_ALERTES', ''),
    'signature' => ['nom' => 'Emmanuel Taochy', 'fonction' => 'Fondateur de Swap’Îles'],

    // Montée en douceur d'une adresse neuve : mails par jour selon l'ancienneté (jours).
    'montee' => [7 => 20, 14 => 40],
    'plafond_max' => (int) env('AGENT_PLAFOND_MAX', 80),

    // Envois répartis sur la journée (heure de La Réunion), du lundi au samedi.
    'heure_debut' => 8,
    'heure_fin' => 16,

    // Une seule relance pour les organismes publics, sans réponse au bout de N jours.
    'relance_jours' => 7,

    // Types de l'annuaire officiel de l'administration (service-public.fr) prospectés.
    'rse_types' => [
        'mairie' => 'Mairie',
        'ccas' => 'CCAS',
        'epci' => 'Intercommunalité',
        'cg' => 'Conseil départemental',
        'cr' => 'Conseil régional',
        'ctu' => 'Collectivité territoriale',
        'prefecture' => 'Préfecture',
        'mission_locale' => 'Mission locale',
    ],

    // Lecture des réponses (boîte contact@, accès déjà utilisé par le site pour envoyer).
    'imap' => [
        // Boîte contact@ hébergée chez Hostinger : imap.hostinger.com, sauf réglage contraire.
        'hote' => env('AGENT_IMAP_HOST') ?: (filled(env('AGENT_IMAP_USERNAME')) ? 'imap.hostinger.com' : preg_replace('/^smtp\./', 'imap.', (string) env('MAIL_HOST', ''))),
        'port' => (int) env('AGENT_IMAP_PORT', 993),
        'utilisateur' => env('AGENT_IMAP_USERNAME', env('MAIL_USERNAME')),
        'mot_de_passe' => env('AGENT_IMAP_PASSWORD', env('MAIL_PASSWORD')),
    ],

    // Adresses de messagerie grand public : jamais rapprochées par le domaine.
    'domaines_publics' => ['gmail.com', 'hotmail.com', 'hotmail.fr', 'outlook.com', 'outlook.fr', 'live.fr', 'yahoo.fr', 'yahoo.com',
        'orange.fr', 'wanadoo.fr', 'laposte.net', 'free.fr', 'sfr.fr', 'icloud.com', 'gmx.fr', 'live.com', 'msn.com', 'outremer-telecom.fr', 'zeop.re', 'canalsat.re'],
];

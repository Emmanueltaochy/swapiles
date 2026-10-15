<?php

/*
| Programme de parrainage.
|
| Désactivé par défaut (REFERRAL_ENABLED). Tant qu'il l'est, seuls les
| administrateurs et les e-mails listés dans REFERRAL_TESTEURS y ont accès :
| leurs liens d'invitation fonctionnent pour tester en production, rien
| n'apparaît pour les autres membres.
*/
return [
    'enabled' => (bool) env('REFERRAL_ENABLED', false),

    'testeurs' => array_filter(array_map('trim', explode(',', (string) env('REFERRAL_TESTEURS', '')))),

    // Crédit versé au parrain quand le 1er achat de son filleul est terminé.
    'reward_amount' => (float) env('REFERRAL_REWARD_AMOUNT', 0),

    // Durée de validité d'un crédit, en jours.
    'credit_validity_days' => (int) env('REFERRAL_CREDIT_VALIDITY_DAYS', 90),

    // Récompenses maximum par parrain et par mois civil.
    'max_rewards_per_month' => (int) env('REFERRAL_MAX_REWARDS_PER_MONTH', 10),

    // Paliers : badge « Ambassadeur » puis « à mettre en avant » (admin).
    'palier_ambassadeur' => (int) env('REFERRAL_PALIER_AMBASSADEUR', 3),
    'palier_mise_en_avant' => (int) env('REFERRAL_PALIER_MISE_EN_AVANT', 10),

    // Saisie manuelle d'un code possible pendant N jours après l'inscription.
    'delai_saisie_jours' => 7,

    // Lien différé : invitation ouverte → 1re ouverture de l'appli (même IP).
    'appariement_heures' => 48,

    // RGPD : les ouvertures d'invitation (IP chiffrée) sont effacées après N jours.
    'conservation_invitations_jours' => 7,

    // Rappel avant expiration d'un crédit.
    'rappel_expiration_jours' => 7,

    // Stripe refuse un paiement sous 0,50 € : le crédit laisse toujours ce minimum à la carte.
    'paiement_minimum_centimes' => 50,

    // Cookie qui garde le code jusqu'à l'inscription.
    'cookie' => 'swp_parrain',
    'cookie_jours' => 30,

    // Redirection automatique vers l'App Store / le Play Store depuis
    // /invite/{code}. À activer quand les liens universels (lot 2) sont en
    // place : avant, un membre qui a déjà l'appli serait envoyé au store.
    'redirection_store' => (bool) env('REFERRAL_REDIRECTION_STORE', false),
];

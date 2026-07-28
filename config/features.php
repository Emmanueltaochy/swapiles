<?php

/*
|--------------------------------------------------------------------------
| Feature flags
|--------------------------------------------------------------------------
| La config n'est PAS mise en cache en prod (deploy = config:clear, pas
| config:cache), donc env() est relu à chaque requête : on peut basculer un
| flag en changeant la variable d'environnement puis en rechargeant PHP-FPM.
*/

return [
    /*
     | Point 19 — Différer le KYC Stripe à la première vente.
     | ON  : un vendeur peut publier et activer le paiement CB sans dossier
     |       Stripe complet ; le KYC (identité + IBAN) est sollicité au moment
     |       de la vente. Les fonds restent sur le compte plateforme jusqu'au
     |       virement, donc aucun risque : si le vendeur ne complète jamais,
     |       l'acheteur est remboursé (filet AutoResolveTransactions).
     | OFF : ancien comportement (KYC complet requis avant d'activer la CB).
     */
    'defer_kyc' => filter_var(env('FEATURE_DEFER_KYC', true), FILTER_VALIDATE_BOOLEAN),

    /*
     | E-mail de sollicitation KYC à la vente (« X € t'attendent, ajoute ton
     | IBAN »). Le bandeau in-app reste actif quoi qu'il arrive ; ce flag ne
     | contrôle que l'envoi de l'e-mail, activé par défaut. On coupera si la
     | délivrabilité pose problème.
     */
    'sale_kyc_email' => filter_var(env('FEATURE_SALE_KYC_EMAIL', true), FILTER_VALIDATE_BOOLEAN),
];

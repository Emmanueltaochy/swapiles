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

    /*
     | Modération Partie 1 — détection par mots-clés à l'envoi d'un message
     | (sans IA : instantané, gratuit, déterministe).
     | ON  : les messages contenant un mot-clé de paiement hors plateforme
     |       (wero, paypal, rib, iban…) sont bloqués à l'envoi avec un
     |       avertissement et un bouton « Envoyer quand même » (Option A).
     | OFF : aucun blocage (les messages partent normalement).
     */
    'moderation_keyword_block' => filter_var(env('FEATURE_MODERATION_KEYWORD_BLOCK', true), FILTER_VALIDATE_BOOLEAN),

    /*
     | Mode de blocage des mots-clés paiement :
     |  'A' (défaut, recommandé) : blocage à l'envoi + « Envoyer quand même »
     |      (ne punit pas le vendeur qui REFUSE le paiement hors plateforme).
     |  'B' : le message n'est pas délivré du tout (plus strict, déconseillé).
     */
    'moderation_block_mode' => strtoupper((string) env('MODERATION_BLOCK_MODE', 'A')) === 'B' ? 'B' : 'A',
];

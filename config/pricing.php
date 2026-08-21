<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Protection acheteur
    |--------------------------------------------------------------------------
    | protection = clamp( round(prix * rate, 2, HALF_UP), floor, cap )
    |
    | Les 3 constantes sont configurables via .env SANS redéploiement
    | (la config n'est pas mise en cache en production, donc env() est lu au
    | runtime). Valeurs en EUROS.
    */
    'protection_rate' => (float) env('PRICING_PROTECTION_RATE', 0.10),
    'protection_floor' => (float) env('PRICING_PROTECTION_FLOOR', 0.50),
    'protection_cap' => (float) env('PRICING_PROTECTION_CAP', 15.00),

    /*
    |--------------------------------------------------------------------------
    | Commission vendeur
    |--------------------------------------------------------------------------
    | 0 % : le vendeur reçoit EXACTEMENT le prix affiché sur son annonce.
    | Le seul revenu plateforme est la protection acheteur.
    */
    'seller_commission_rate' => (float) env('PRICING_SELLER_COMMISSION_RATE', 0.0),

    /*
    |--------------------------------------------------------------------------
    | Point relais (commerçant partenaire)
    |--------------------------------------------------------------------------
    | Frais ajoutés au total quand l'acheteur choisit un point relais.
    |   relay_fee          = surcoût payé par l'acheteur (ex. 3,00 €)
    |   relay_merchant_fee = part reversée au commerçant (ex. 1,00 €)
    |   part plateforme    = relay_fee - relay_merchant_fee (ex. 2,00 €)
    | Le commerçant ne manipule jamais l'argent : tout transite par la CB.
    | Configurable via .env sans redéploiement (config non cachée en prod).
    */
    'relay_fee' => (float) env('PRICING_RELAY_FEE', 3.00),
    'relay_merchant_fee' => (float) env('PRICING_RELAY_MERCHANT_FEE', 1.00),
];

<?php

/**
 * Alertes e-mail envoyées à l'administration.
 *
 * La boîte contact@swapiles.com est plafonnée à 1 000 e-mails par jour par
 * l'hébergeur. Au-delà, TOUS les envois sont ralentis : les e-mails importants
 * (confirmation d'adresse, mot de passe oublié, vente) arrivent des heures plus
 * tard, voire en indésirable. Chaque alerte d'administration consomme un envoi.
 *
 * Les évènements courants (inscription, dépôt d'annonce, favori) sont déjà
 * visibles en temps réel dans Admin > Activité & Emails : les envoyer aussi par
 * e-mail ne sert à rien et sature le quota. Ils sont donc désactivés ici.
 *
 * Mettre une clé à true pour recevoir de nouveau l'alerte par e-mail.
 */
return [

    // --- Évènements courants : consultables dans Admin > Activité & Emails ---
    'user_registered' => false,   // nouvel inscrit
    'listing_published' => false, // nouvelle annonce
    'favorite_added' => false,    // annonce ajoutée en favori

    // --- Évènements importants : toujours par e-mail ---
    'sale_completed' => true,        // nouvelle vente validée
    'stripe_payment' => true,        // paiement Stripe confirmé
    'report_submitted' => true,      // signalement d'un membre ou d'une annonce
    'offplatform_payment' => true,   // paiement hors plateforme signalé
    'account_deleted' => true,       // suppression de compte (RGPD)
    'relay_request' => true,         // candidature point relais
    'refund_review' => true,         // remboursement à valider

];

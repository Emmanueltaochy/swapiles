<?php

/**
 * Garde-fous de volume d'envoi.
 *
 * La boîte contact@swapiles.com est plafonnée à 1 000 e-mails par jour par
 * l'hébergeur. Une fois le plafond atteint, TOUS les envois sont ralentis :
 * les e-mails importants (confirmation d'adresse, mot de passe oublié, vente)
 * arrivent des heures plus tard, voire jamais. Les e-mails d'animation doivent
 * donc rester nettement en dessous du plafond.
 */
return [

    /*
     * E-mail « quelqu'un a regardé votre annonce ».
     *
     * Avant : un e-mail par visiteur ET par annonce toutes les 24 h. Sur une
     * boutique de 30 annonces vue par 20 visiteurs, cela représentait à soi
     * seul des centaines d'envois par jour.
     *
     * Maintenant : une annonce ne déclenche qu'un e-mail par 24 h, et un
     * vendeur n'en reçoit pas plus de « par_vendeur_par_jour ».
     */
    'listing_view' => [
        'par_annonce_par_heures' => 24,
        'par_vendeur_par_jour' => 3,
    ],

];

<?php

/*
 * « Recommandé pour vous » : réglages de l'envoi.
 *
 * Le but est d'envoyer DE TEMPS EN TEMPS une sélection qui fait plaisir,
 * jamais un flot de notifications : un envoi au plus tous les
 * « intervalle_jours », le soir (heure de l'île du membre), et seulement si
 * la sélection est vraiment pertinente.
 */
return [

    // Jours d'historique (articles ouverts, favoris) pris en compte.
    'jours_signaux' => 45,

    // Poids minimal de l'historique pour oser recommander : un favori vaut 3,
    // un article ouvert vaut 1. En dessous, on ne connaît pas assez le membre.
    'signal_minimum' => 3,

    // Un envoi au plus tous les N jours.
    'intervalle_jours' => 4,

    // Créneau d'envoi, heure locale de l'île du membre (début inclus, fin exclue).
    'heure_debut' => 18,
    'heure_fin' => 21,

    // Il faut au moins N articles pertinents pour envoyer.
    'minimum_articles' => 2,

    // Articles retenus par envoi (affichés sur la page « Pour vous »).
    'articles_par_envoi' => 12,

    // On ne recommande que des articles publiés depuis moins de N jours.
    'fraicheur_jours' => 30,

    // Note minimale d'un article pour être recommandé.
    'score_minimum' => 3.0,

    // Au plus N articles d'un même vendeur par sélection.
    'max_par_vendeur' => 2,
];

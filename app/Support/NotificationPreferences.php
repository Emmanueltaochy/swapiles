<?php

namespace App\Support;

/**
 * Réglages de notification d'un membre.
 *
 * Jusqu'ici, un membre ne pouvait RIEN couper : ni les notifications push, ni
 * les e-mails d'animation. Le seul moyen d'arrêter d'être sollicité était de
 * supprimer son compte. C'est une cause directe de départs.
 *
 * Les notifications liées à une VENTE (paiement, expédition, remboursement,
 * modération) ne sont jamais désactivables : ce sont des informations dont le
 * membre a besoin, pas de l'animation.
 */
class NotificationPreferences
{
    /** Catégories réglables, avec leur libellé et les types de notification couverts. */
    public const CATEGORIES = [
        'messages' => [
            'label' => 'Messages reçus',
            'description' => 'Quand un membre vous écrit.',
            'types' => ['message_received', 'exchange_proposal', 'listing_interest'],
        ],
        'favoris' => [
            'label' => 'Favoris sur mes annonces',
            'description' => 'Quand quelqu’un met une de vos annonces en favori.',
            'types' => ['favorite_added'],
        ],
        'vendeurs_suivis' => [
            'label' => 'Nouveautés des vendeurs suivis',
            'description' => 'Quand un vendeur que vous suivez publie une annonce.',
            'types' => ['seller_published_listing', 'listing_available_colissimo'],
        ],
        'conseils' => [
            'label' => 'Conseils et rappels',
            'description' => 'Annonce sans photo, relances, astuces de vente.',
            'types' => ['listing_needs_photo', 'account_onboarding'],
        ],
    ];

    /** Types toujours envoyés : ils concernent l'argent ou la sécurité du compte. */
    public const TOUJOURS_ENVOYES = [
        'transaction_paid_buyer',
        'transaction_paid_seller',
        'transaction_refunded',
        'offer_accepted',
        'offer_declined',
        'moderation_warning',
        'user_deleted',
    ];

    /** Réglages par défaut : tout activé (comportement actuel). */
    public static function defauts(): array
    {
        $defauts = [];

        foreach (array_keys(self::CATEGORIES) as $cle) {
            $defauts[$cle] = ['push' => true, 'email' => true];
        }

        return $defauts;
    }

    /** Catégorie d'un type de notification, ou null si le type est toujours envoyé. */
    public static function categorieDuType(?string $type): ?string
    {
        if ($type === null || in_array($type, self::TOUJOURS_ENVOYES, true)) {
            return null;
        }

        foreach (self::CATEGORIES as $cle => $categorie) {
            if (in_array($type, $categorie['types'], true)) {
                return $cle;
            }
        }

        // Type inconnu : on l'envoie (on ne fait jamais taire par accident).
        return null;
    }

    /**
     * Nettoie un tableau venant d'un formulaire : seules les clés connues sont
     * retenues, et chaque valeur devient un booléen.
     */
    public static function nettoyer(mixed $valeurs): array
    {
        $valeurs = is_array($valeurs) ? $valeurs : [];
        $propre = [];

        foreach (array_keys(self::CATEGORIES) as $cle) {
            $propre[$cle] = [
                'push' => (bool) ($valeurs[$cle]['push'] ?? false),
                'email' => (bool) ($valeurs[$cle]['email'] ?? false),
            ];
        }

        return $propre;
    }
}

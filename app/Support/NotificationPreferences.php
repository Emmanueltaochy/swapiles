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
    /**
     * Catégories réglables, avec leur libellé, les types couverts et leur
     * réglage PAR DÉFAUT.
     *
     * Activé d'office : ce qu'un membre attend (un message, une proposition
     * d'échange, une nouveauté d'un dressing qu'il a choisi de suivre).
     * Désactivé d'office : les vues et les mises en favori. Trop fréquentes,
     * elles noyaient les notifications utiles ; chacun peut les activer.
     */
    public const CATEGORIES = [
        'messages' => [
            'label' => 'Messages et propositions',
            'description' => 'Quand un membre vous écrit ou vous propose un échange.',
            'types' => ['message_received', 'exchange_proposal', 'listing_interest'],
            'defaut' => true,
        ],
        'vendeurs_suivis' => [
            'label' => 'Dressings que vous suivez',
            'description' => 'Quand un membre que vous suivez publie un article.',
            'types' => ['seller_published_listing', 'listing_available_colissimo'],
            'defaut' => true,
        ],
        'favoris' => [
            'label' => 'Mises en favori',
            'description' => 'Quand quelqu’un ajoute une de vos annonces à ses favoris.',
            'types' => ['favorite_added'],
            'defaut' => false,
        ],
        'vues' => [
            'label' => 'Vues de vos annonces',
            'description' => 'Quand quelqu’un consulte une de vos annonces.',
            'types' => ['listing_viewed'],
            'defaut' => false,
        ],
        'recommandations' => [
            'label' => 'Recommandé pour vous',
            'description' => 'De temps en temps, une sélection d’articles qui ressemblent à ce que vous regardez et aimez.',
            'types' => ['recommandations'],
            'defaut' => true,
            // Notification mobile seulement : pas d'e-mail pour ces suggestions.
            'canaux' => ['push'],
        ],
        'conseils' => [
            'label' => 'Conseils et rappels',
            'description' => 'Annonce sans photo, relances, astuces de vente.',
            'types' => ['listing_needs_photo', 'account_onboarding'],
            'defaut' => true,
        ],
    ];

    /** Types toujours envoyés : ils concernent l'argent ou la sécurité du compte. */
    public const TOUJOURS_ENVOYES = [
        'transaction_paid_buyer',
        'transaction_paid_seller',
        'transaction_refunded',
        'offer_received',
        'offer_accepted',
        'offer_declined',
        'moderation_warning',
        'user_deleted',
    ];

    /** Réglages par défaut de chaque catégorie (voir CATEGORIES). */
    public static function defauts(): array
    {
        $defauts = [];

        foreach (self::CATEGORIES as $cle => $categorie) {
            $defauts[$cle] = [
                'push' => $categorie['defaut'] && self::aLeCanal($cle, 'push'),
                'email' => $categorie['defaut'] && self::aLeCanal($cle, 'email'),
            ];
        }

        return $defauts;
    }

    /** Réglage par défaut d'une catégorie (activée tant qu'on ne sait pas). */
    public static function parDefaut(string $categorie): bool
    {
        return (bool) (self::CATEGORIES[$categorie]['defaut'] ?? true);
    }

    /** La catégorie propose-t-elle ce canal (mobile, e-mail) ? */
    public static function aLeCanal(string $categorie, string $canal): bool
    {
        return in_array($canal, self::CATEGORIES[$categorie]['canaux'] ?? ['push', 'email'], true);
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

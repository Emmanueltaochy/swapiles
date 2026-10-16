<?php

namespace App\Support;

/**
 * Bio du membre, affichée sous son nom (Mon compte, profil public).
 *
 * Elle est publique : on y refuse ce qui ferait sortir de la plateforme
 * (téléphone, e-mail, lien, paiement en dehors de Swap'Îles).
 */
class Bio
{
    public const MAX = 150;

    /** Espaces nettoyés, 4 lignes au plus. */
    public static function nettoyer(?string $bio): ?string
    {
        $bio = str_replace(["\r\n", "\r"], "\n", (string) $bio);
        $lignes = array_map(fn ($l) => trim(preg_replace('/[ \t]+/u', ' ', $l)), explode("\n", $bio));
        $lignes = array_slice(array_values(array_filter($lignes, fn ($l) => $l !== '')), 0, 4);
        $bio = trim(implode("\n", $lignes));

        return $bio === '' ? null : $bio;
    }

    /** Message à afficher si la bio est refusée, sinon null. */
    public static function erreur(?string $bio): ?string
    {
        if ($bio === null) {
            return null;
        }

        if (mb_strlen($bio) > self::MAX) {
            return 'Ta bio fait plus de ' . self::MAX . ' caractères.';
        }

        if (preg_match('/[\w.+-]+@[\w-]+\.[a-z]{2,}/iu', $bio)
            || preg_match('/(https?:\/\/|www\.|\b[\w-]+\.(com|fr|re|net|org|io|me|ly)\b)/iu', $bio)) {
            return 'Pas de lien ni d’adresse e-mail dans la bio : tout se passe sur Swap’Îles.';
        }

        if (MessageModeration::detectPhone($bio) || preg_match('/(?:\d[\s.\-]*){8,}/u', $bio)) {
            return 'Pas de numéro de téléphone ni d’appli de contact dans la bio.';
        }

        if (MessageModeration::detectPayment($bio) !== []) {
            return 'Pas de moyen de paiement en dehors de Swap’Îles dans la bio.';
        }

        return null;
    }
}

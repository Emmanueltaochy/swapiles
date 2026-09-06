<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Île « courante » d'un visiteur ou d'un membre.
 *
 * Un seul endroit décide de l'île affichée, pour éviter que la page d'accueil,
 * la recherche et le compteur de visites ne se contredisent.
 *
 * Règle : le cookie porte le choix de navigation (le sélecteur d'île), mais il
 * est RÉALIGNÉ sur l'île du profil à chaque connexion et à chaque modification
 * du profil. Sans ça, un cookie posé un an plus tôt — souvent par un AUTRE
 * compte utilisé sur le même téléphone — faisait basculer un membre réunionnais
 * sur une autre île à son insu.
 */
class TerritoireContext
{
    public const COOKIE = 'swapiles_territoire';

    public const DEFAUT = 'La Réunion';

    /** Un an : le choix d'île n'a pas de raison d'expirer. */
    public const DUREE_MINUTES = 60 * 24 * 365;

    /** Libellés stockés en base (colonne territoire), dans l'ordre d'affichage. */
    public const LABELS = ['La Réunion', 'Guyane', 'Martinique', 'Guadeloupe', 'Mayotte'];

    /** Clé d'URL (/territoire/{clé}) => libellé stocké. */
    public const CLES = [
        'reunion' => 'La Réunion',
        'guyane' => 'Guyane',
        'martinique' => 'Martinique',
        'guadeloupe' => 'Guadeloupe',
        'mayotte' => 'Mayotte',
    ];

    public static function isValid(?string $label): bool
    {
        return $label !== null && in_array($label, self::LABELS, true);
    }

    /** Île du cookie, si elle est exploitable. */
    public static function fromCookie(Request $request): ?string
    {
        $cookie = $request->cookie(self::COOKIE);

        return self::isValid(is_string($cookie) ? $cookie : null) ? $cookie : null;
    }

    /** Île du profil du membre connecté, si elle est exploitable. */
    public static function fromProfile(Request $request): ?string
    {
        $profil = $request->user()?->territoire;

        return self::isValid($profil) ? $profil : null;
    }

    /**
     * Île à utiliser pour la requête courante.
     * Choix de navigation (cookie) > île du profil > La Réunion.
     */
    public static function resolve(Request $request): string
    {
        return self::fromCookie($request)
            ?? self::fromProfile($request)
            ?? self::DEFAUT;
    }

    /** Vrai si l'île vient d'un vrai choix (cookie ou profil), pas du défaut. */
    public static function isKnown(Request $request): bool
    {
        return self::fromCookie($request) !== null || self::fromProfile($request) !== null;
    }

    /** Cookie de choix d'île, à attacher à une réponse (withCookie). */
    public static function cookie(string $label): SymfonyCookie
    {
        return cookie(self::COOKIE, $label, self::DUREE_MINUTES);
    }

    /**
     * Recale le cookie sur l'île du membre : appelé à la connexion et après une
     * modification du profil, pour qu'un choix laissé par un autre compte sur le
     * même appareil ne prenne jamais le dessus.
     */
    public static function rememberForUser(?User $user): void
    {
        if ($user && self::isValid($user->territoire)) {
            Cookie::queue(self::cookie($user->territoire));

            return;
        }

        Cookie::queue(Cookie::forget(self::COOKIE));
    }
}

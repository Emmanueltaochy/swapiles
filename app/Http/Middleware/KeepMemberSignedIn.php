<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rester connecté, comme sur Instagram.
 *
 * Le cookie « Rester connecté » était posé une seule fois, à la connexion, pour
 * 400 jours. Un membre actif tous les jours était donc déconnecté au bout de
 * ce délai, et un membre connecté avant que la case soit cochée par défaut
 * n'en avait jamais eu : à l'expiration de sa session, il se retrouvait
 * déconnecté sans comprendre pourquoi.
 *
 * Désormais, tant qu'on utilise l'appli, la mémorisation est renouvelée (une
 * fois par jour suffit) : on n'est déconnecté que si on le demande, si on
 * change de mot de passe, ou après plus d'un an sans ouvrir l'appli.
 *
 * Seule exception : la personne qui a DÉCOCHÉ « Rester connecté » (ordinateur
 * partagé). Son choix est respecté.
 */
class KeepMemberSignedIn
{
    /** Clé de session : date du dernier renouvellement. */
    public const RENOUVELE_LE = 'swp_memorisation_renouvelee';

    /** Clé de session : la personne a décoché « Rester connecté ». */
    public const SANS_MEMORISATION = 'swp_sans_memorisation';

    /**
     * 400 jours, en minutes : la durée que Laravel donne à ce cookie, et le
     * maximum qu'acceptent les navigateurs. Renouvelée chaque jour d'usage,
     * elle ne s'épuise jamais pour un membre actif.
     */
    public const DUREE_MINUTES = 576000;

    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        $guard = Auth::guard();

        if (! $guard instanceof SessionGuard || ! $guard->check() || ! $request->hasSession()) {
            return $reponse;
        }

        $session = $request->session();

        if ($session->get(self::SANS_MEMORISATION)) {
            return $reponse;
        }

        $aujourdhui = now()->toDateString();
        if ($session->get(self::RENOUVELE_LE) === $aujourdhui && $request->cookies->has($guard->getRecallerName())) {
            return $reponse;
        }

        $membre = $guard->user();

        // Compte connecté sans jeton de mémorisation (connexion ancienne) : on
        // lui en donne un, sans toucher aux autres appareils.
        if (blank($membre->getRememberToken())) {
            $membre->setRememberToken($jeton = Str::random(60));
            $guard->getProvider()->updateRememberToken($membre, $jeton);
        }

        // Même valeur que celle que Laravel pose à la connexion : seule la
        // date d'expiration est repoussée.
        Cookie::queue(Cookie::make(
            $guard->getRecallerName(),
            $membre->getAuthIdentifier() . '|' . $membre->getRememberToken() . '|' . $guard->hashPasswordForCookie($membre->getAuthPassword()),
            self::DUREE_MINUTES
        ));

        $session->put(self::RENOUVELE_LE, $aujourdhui);

        return $reponse;
    }
}

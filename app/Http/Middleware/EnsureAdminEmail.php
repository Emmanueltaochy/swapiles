<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Protège l'espace d'administration (/admin).
 *
 * Un membre non administrateur qui atterrit ici (par exemple en se connectant
 * via la page de connexion de l'admin) est simplement RENVOYÉ VERS LE SITE, en
 * restant connecté : auparavant il était déconnecté et bloqué sur un 403 sans
 * issue. La liste des administrateurs vient de User::isAdmin() (ADMIN_EMAILS).
 */
class EnsureAdminEmail
{
    public function handle(Request $request, Closure $next)
    {
        if (! auth()->check()) {
            return $next($request);
        }

        if (! auth()->user()->isAdmin()) {
            return redirect()->route('home')
                ->with('status', "Cet espace est réservé à l'administration de Swap'Îles.");
        }

        return $next($request);
    }
}

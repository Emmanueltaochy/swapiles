<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * L'administration s'affiche en français (boutons, « il y a 8 minutes »,
 * dates, listes vides), quelle que soit la langue par défaut de l'appli.
 */
class AdminEnFrancais
{
    public function handle(Request $request, Closure $next)
    {
        app()->setLocale('fr');
        Carbon::setLocale('fr');

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\LiensStores;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Retient la source d'une visite venue d'une pub (utm_source, utm_medium,
 * utm_campaign) le temps de la session : les boutons App Store / Google Play
 * du site la transmettent ensuite aux stores.
 */
class RetenirSourcePub
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && $request->filled('utm_source') && $request->hasSession()) {
            $request->session()->put(LiensStores::SESSION, [
                'source' => LiensStores::nettoyer($request->query('utm_source')),
                'medium' => LiensStores::nettoyer($request->query('utm_medium')),
                'campaign' => LiensStores::nettoyer($request->query('utm_campaign')),
            ]);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force la génération des URLs sur le domaine canonique (swapiles.com) pour
 * les pages publiques, quel que soit le domaine par lequel la page a été
 * servie (ex : admin.swapiles.com).
 *
 * Sans cela, un lien de partage ou une balise OpenGraph générée pendant une
 * navigation sur admin.swapiles.com pointerait vers ce sous-domaine au lieu de
 * swapiles.com. Le panneau d'administration est exclu pour ne pas casser
 * Filament/Livewire qui doivent rester sur leur propre hôte.
 */
class ForceCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        // On n'intervient pas sur l'admin et Livewire (hôte propre à Filament).
        if (! $request->is('admin*') && ! $request->is('livewire*')) {
            $canonical = env('APP_CANONICAL_URL', 'https://swapiles.com');

            if (is_string($canonical) && $canonical !== '') {
                URL::forceRootUrl($canonical);
                URL::forceScheme('https');
            }
        }

        return $next($request);
    }
}

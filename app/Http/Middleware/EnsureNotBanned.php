<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    /**
     * Déconnecte immédiatement tout utilisateur banni, quel que soit le moment
     * où le bannissement a été appliqué depuis l'admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->is_banned) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('home')->withErrors([
                'email' => 'Votre compte a été suspendu. Contactez contact@swapiles.com.',
            ]);
        }

        return $next($request);
    }
}

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\TrackLiveVisit;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Le site est derrière un proxy/CDN (nginx + éventuellement Cloudflare).
        // Sans ceci, $request->ip() renverrait l'IP du proxy et toutes les
        // connexions auraient la même IP. On fait confiance au proxy pour lire
        // la vraie IP client dans les en-têtes X-Forwarded-* (le port applicatif
        // n'est joignable que via le proxy, jamais directement depuis Internet).
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\TrackAnalyticsPageView::class,
        ]);

        $middleware->prependToGroup('web', \App\Http\Middleware\ForceCanonicalHost::class);
        $middleware->appendToGroup('web', TrackLiveVisit::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureNotBanned::class);
        // Après EnsureNotBanned : un compte suspendu n'est jamais prolongé.
        $middleware->appendToGroup('web', \App\Http\Middleware\KeepMemberSignedIn::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JETON DE SÉCURITÉ PÉRIMÉ (erreur 419).
        //
        // Une page restée ouverte des jours dans l'appli garde un jeton
        // périmé : le premier envoi tombait sur « 419 Page expirée », que les
        // membres prenaient pour une déconnexion. On ne montre plus jamais cet
        // écran : on revient sur la page, saisie conservée, avec une phrase
        // claire. Rien n'a été envoyé, par sécurité.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            // Requête faite en JavaScript (favori, signalement…) : on renvoie
            // un jeton neuf pour que le script puisse réessayer.
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Votre page était ouverte depuis longtemps. Réessayez.',
                    'jeton' => csrf_token(),
                ], 419);
            }

            // Se déconnecter avec un jeton périmé : on fait simplement ce que
            // la personne demandait.
            if ($request->routeIs('logout')) {
                \Illuminate\Support\Facades\Auth::guard()->logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('home');
            }

            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                ->with('swp_info', 'Votre page était ouverte depuis longtemps : par sécurité, rien n\'a été envoyé. Vérifiez et renvoyez.');
        });
    })->create();

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
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

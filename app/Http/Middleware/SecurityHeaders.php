<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // En-têtes de durcissement (sans CSP pour ne pas casser Stripe/Pixel/inline).
        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',              // anti-clickjacking
            'X-Content-Type-Options' => 'nosniff',           // anti MIME-sniffing
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-XSS-Protection' => '0',
            // Caméra et micro : seulement sur les pages de vente en direct.
            'Permissions-Policy' => $request->is('live/*')
                ? 'geolocation=(), microphone=(self), camera=(self)'
                : 'geolocation=(), microphone=(), camera=()',
        ];

        foreach ($headers as $key => $value) {
            if (! $response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdminEmail
{
    public function handle(Request $request, Closure $next)
    {
        $allowedEmail = 'cabinet@taochyconsulting.fr';

        if (!auth()->check()) {
            return $next($request);
        }

        if (strtolower((string) auth()->user()->email) !== strtolower($allowedEmail)) {
            auth()->logout();

            abort(403, 'Accès admin non autorisé.');
        }

        return $next($request);
    }
}

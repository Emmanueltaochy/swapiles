<?php

namespace App\Http\Middleware;

use App\Support\LiveAcces;
use Closure;
use Illuminate\Http\Request;

/** Phase de test : le live n'existe que pour les admins et testeurs. */
class LiveVisible
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(LiveAcces::visible($request->user()), 404);

        return $next($request);
    }
}

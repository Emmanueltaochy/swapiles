<?php

namespace App\Http\Middleware;

use App\Support\LiveAcces;
use Closure;
use Illuminate\Http\Request;

/** Lancer un live : réservé aux membres autorisés (voir LiveAcces). */
class LivePeutAnimer
{
    public function handle(Request $request, Closure $next)
    {
        if (! LiveAcces::peutAnimer($request->user())) {
            return redirect()->route('live.index')->with('status', 'Les lives sont réservés aux vendeurs invités par Swap’Îles pour le moment. Écrivez-nous à contact@swapiles.com pour lancer le vôtre !');
        }

        return $next($request);
    }
}

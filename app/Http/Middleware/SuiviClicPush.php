<?php

namespace App\Http\Middleware;

use App\Support\PushSuivi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lien ouvert depuis une notification push (« ?pn=345 ») : on note le clic,
 * puis on affiche la page demandée sans le repère (lien propre, partageable).
 */
class SuiviClicPush
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->query(PushSuivi::PARAMETRE);

        if (! $request->isMethod('GET') || ! is_string($id) || ! ctype_digit($id)) {
            return $next($request);
        }

        try {
            PushSuivi::clic($request, (int) $id);
        } catch (\Throwable $e) {
            report($e);
        }

        $requete = $request->query();
        unset($requete[PushSuivi::PARAMETRE]);
        $propre = $request->getPathInfo() . ($requete ? '?' . http_build_query($requete) : '');

        return redirect()->to($propre);
    }
}

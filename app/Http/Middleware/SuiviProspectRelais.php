<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ProspectionRelaisController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Commerce venu d'un mail de prospection (clic sur le bouton) : chaque page
 * qu'il ouvre ensuite est notée dans son parcours (admin « Prospection relais »).
 */
class SuiviProspectRelais
{
    private const PAGES = [
        'relay.partner' => '📄 Page « Devenir point relais »',
        'relay.charte' => '📄 Charte des points relais',
        'relay.points' => '🗺️ Carte des points relais',
        'home' => '🏠 Accueil du site',
        'comment-ca-marche' => '📄 « Comment ça marche »',
        'partenaires.index' => '📄 Page partenaires',
        'register' => '👤 Page d’inscription',
        'login' => '👤 Page de connexion',
        'account.relay.dashboard' => '🏪 Espace point relais',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        // Préchargement (lien survolé) : ce n'est pas une visite.
        if (! $request->isMethod('GET') || $request->expectsJson() || ! $request->hasSession() || ! $request->session()->has('relais_prospect')
            || \App\Http\Controllers\ListingController::estPrechargement($request)) {
            return $reponse;
        }

        try {
            if ($prospect = ProspectionRelaisController::prospectSuivi()) {
                $nom = $request->route()?->getName();
                $page = self::PAGES[$nom] ?? '📄 ' . '/' . ltrim($request->path(), '/');
                // Même page rouverte juste après (ancre, retour) : une seule ligne.
                $recentes = collect(array_slice($prospect->parcours ?? [], -3))->pluck('e');
                if ($nom !== 'relais.prospection.clic' && ! $recentes->contains($page)) {
                    $prospect->noterEtape($page);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $reponse;
    }
}

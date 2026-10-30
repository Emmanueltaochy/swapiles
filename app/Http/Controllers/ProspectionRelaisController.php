<?php

namespace App\Http\Controllers;

use App\Models\RelaisProspect;

/** Lien « Ne plus être contacté » des mails de prospection des points relais. */
class ProspectionRelaisController extends Controller
{
    /**
     * Bouton « Découvrir et devenir point relais » du mail : on note le clic
     * (suivi dans l'admin), puis on ouvre la page partenaire, formulaire pré-rempli.
     */
    public function clic(string $jeton)
    {
        $prospect = RelaisProspect::where('jeton', $jeton)->first();
        if ($prospect) {
            $prospect->forceFill([
                'clique_at' => $prospect->clique_at ?? now(),
                'nb_clics' => $prospect->nb_clics + 1,
            ])->save();
            $prospect->noterEtape('✉️ A cliqué sur le bouton du mail');
            session(['relais_prospect' => $prospect->id, 'relais_prospect_jusqu' => now()->addDays(14)->timestamp]);
        }

        return redirect()->route('relay.partner', ['utm_source' => 'prospection', 'utm_medium' => 'email', 'utm_campaign' => 'relais']);
    }

    /** Boutons touchés sur la page partenaire (envoyés discrètement par la page). */
    public const ACTIONS = [
        'devenir' => '👉 A touché « Devenir partenaire »',
        'comment' => '❓ A touché « Comment ça marche »',
        'mail' => '✉️ A touché l’adresse e-mail de contact',
        'moitie' => '📖 A lu la moitié de la page',
        'bas' => '📖 A lu toute la page',
        'formulaire' => '✍️ A commencé à remplir le formulaire',
    ];

    public function action(\Illuminate\Http\Request $request)
    {
        $prospect = self::prospectSuivi();
        $etape = self::ACTIONS[(string) $request->input('a')] ?? null;
        if ($prospect && $etape) {
            $prospect->noterEtape($etape);
        }

        return response()->noContent();
    }

    /** Commerce venu d'un mail de prospection, pendant 14 jours après le clic. */
    public static function prospectSuivi(): ?RelaisProspect
    {
        $id = (int) session('relais_prospect');
        if (! $id || (int) session('relais_prospect_jusqu') < now()->timestamp) {
            return null;
        }

        return RelaisProspect::find($id);
    }

    public function desinscrire(string $jeton)
    {
        $prospect = RelaisProspect::where('jeton', $jeton)->first();
        $prospect?->forceFill(['statut' => 'desinscrit'])->save();

        return view('relais-prospection-desinscrit', ['nom' => $prospect?->nom]);
    }
}

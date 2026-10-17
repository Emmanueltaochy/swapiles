<?php

namespace App\Http\Controllers;

use App\Models\RelayPoint;
use App\Support\ImpactReemploi;
use App\Support\RapportPartenaire;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Enseignes partenaires (RSE) : page de présentation et rapport d'impact mensuel. */
class PartenairesController extends Controller
{
    public function index()
    {
        return view('partenaires.index', [
            'impact' => ImpactReemploi::bilan()['total'],
            'partenaires' => RelayPoint::query()->active()->whereNotNull('partenaire_enseigne')->orderBy('partenaire_enseigne')->get(),
            'nbRelais' => RelayPoint::query()->active()->count(),
        ]);
    }

    /** Lien privé envoyé à l'enseigne (pas de compte à créer, pas indexé). */
    public function rapport(Request $request, string $jeton)
    {
        $point = RelayPoint::where('rapport_jeton', $jeton)->firstOrFail();

        try {
            $mois = $request->filled('mois') ? Carbon::createFromFormat('Y-m', (string) $request->query('mois'))->startOfMonth() : now()->startOfMonth();
        } catch (\Throwable $e) {
            $mois = now()->startOfMonth();
        }
        if ($mois->gt(now())) {
            $mois = now()->startOfMonth();
        }

        return view('partenaires.rapport', [
            'point' => $point,
            'mois' => $mois,
            'chiffres' => RapportPartenaire::mois($point, $mois),
            'synthese' => RapportPartenaire::synthese($point, $mois),
        ]);
    }
}

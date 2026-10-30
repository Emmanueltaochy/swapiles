<?php

namespace App\Http\Controllers;

use App\Models\RelaisProspect;

/** Lien « Ne plus être contacté » des mails de prospection des points relais. */
class ProspectionRelaisController extends Controller
{
    public function desinscrire(string $jeton)
    {
        $prospect = RelaisProspect::where('jeton', $jeton)->first();
        $prospect?->forceFill(['statut' => 'desinscrit'])->save();

        return view('relais-prospection-desinscrit', ['nom' => $prospect?->nom]);
    }
}

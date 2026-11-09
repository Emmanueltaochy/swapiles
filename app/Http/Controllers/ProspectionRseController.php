<?php

namespace App\Http\Controllers;

use App\Models\ProspectRse;

/** Liens des mails de prospection des organismes publics : découvrir, ne plus être contacté. */
class ProspectionRseController extends Controller
{
    public function clic(string $jeton)
    {
        $p = ProspectRse::where('jeton', $jeton)->first();
        if ($p) {
            $p->forceFill(['clique_at' => $p->clique_at ?? now(), 'nb_clics' => $p->nb_clics + 1])->save();
        }

        return redirect()->route('institutions.reemploi');
    }

    public function desinscrire(string $jeton)
    {
        $p = ProspectRse::where('jeton', $jeton)->first();
        $p?->forceFill(['statut' => 'desinscrit'])->save();

        return view('relais-prospection-desinscrit', ['nom' => $p?->nom]);
    }
}

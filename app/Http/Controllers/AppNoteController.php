<?php

namespace App\Http\Controllers;

use App\Support\NoteAppli;
use App\Support\ParrainageService;
use Illuminate\Http\Request;

/** « swapiles.com/noter » : ouvre la page d'avis du bon store (iPhone ou Android). */
class AppNoteController extends Controller
{
    /** L'appli vient d'ouvrir la fenêtre de notation : on ne la reproposera pas avant 4 mois. */
    public function natif(Request $request)
    {
        if ($request->user()) {
            NoteAppli::natifPropose($request->user());
        }

        return response()->noContent();
    }

    public function __invoke(Request $request)
    {
        $lien = NoteAppli::lienAvis(ParrainageService::plateforme($request->userAgent()));

        // Dans l'appli (version avec la fenêtre de notation intégrée) : la page
        // ouvre la fenêtre d'Apple / Google sans quitter l'appli ; les boutons
        // des stores restent en secours.
        if (str_contains((string) $request->userAgent(), 'SwapilesApp')) {
            return response()->view('app-note', ['ios' => NoteAppli::lienAvis('ios'), 'android' => NoteAppli::lienAvis('android'), 'natif' => true]);
        }

        return $lien
            ? redirect()->away($lien)
            : response()->view('app-note', ['ios' => NoteAppli::lienAvis('ios'), 'android' => NoteAppli::lienAvis('android')]);
    }
}

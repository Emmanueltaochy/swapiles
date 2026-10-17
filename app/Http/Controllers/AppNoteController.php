<?php

namespace App\Http\Controllers;

use App\Support\NoteAppli;
use App\Support\ParrainageService;
use Illuminate\Http\Request;

/** « swapiles.com/noter » : ouvre la page d'avis du bon store (iPhone ou Android). */
class AppNoteController extends Controller
{
    public function __invoke(Request $request)
    {
        $lien = NoteAppli::lienAvis(ParrainageService::plateforme($request->userAgent()));

        return $lien
            ? redirect()->away($lien)
            : response()->view('app-note', ['ios' => NoteAppli::lienAvis('ios'), 'android' => NoteAppli::lienAvis('android')]);
    }
}

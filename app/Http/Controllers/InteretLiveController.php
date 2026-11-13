<?php

namespace App\Http\Controllers;

use App\Support\InteretLive;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Réponses au sondage « live shopping » et demandes d'accès aux lives. */
class InteretLiveController extends Controller
{
    public function repondre(Request $request)
    {
        $data = $request->validate(['choix' => ['required', Rule::in(array_keys(InteretLive::CHOIX))]]);
        $u = $request->user();
        InteretLive::repondre($u, $data['choix']);

        $etape = in_array($data['choix'], ['organiser', 'les_deux'], true) && InteretLive::peutDemander($u) ? 'demande' : 'merci';
        $message = match (true) {
            $etape === 'demande' => null,
            $data['choix'] === 'non' => 'Pas de souci, merci d’avoir répondu !',
            default => 'Super ! On te préviendra dès que les lives ouvrent. 🔴',
        };

        return $request->expectsJson()
            ? response()->json(['etape' => $etape, 'message' => $message])
            : back()->with('status', $message ?? 'Dis-nous ce que tu aimerais vendre en live.')->with('sondage_live_demande', $etape === 'demande');
    }

    public function demander(Request $request)
    {
        $data = $request->validate(['message' => ['nullable', 'string', 'max:500']]);
        $u = $request->user();
        if (InteretLive::peutDemander($u)) {
            InteretLive::demander($u, $data['message'] ?? null);
        }
        $message = 'Demande envoyée ! Emmanuel la regarde et te répond très vite. 🙌';

        return $request->expectsJson() ? response()->json(['etape' => 'merci', 'message' => $message]) : back()->with('status', $message);
    }
}

<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Support\Vacances;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class VacancesController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        return view('account.vacances', [
            'user' => $user,
            'enVacances' => Vacances::enVacances($user),
            // Ventes payées à expédier : à faire avant de partir.
            'aExpedier' => Transaction::where('seller_id', $user->id)
                ->where('status', 'paid')
                ->where('shipping_status', 'pending')
                ->count(),
        ]);
    }

    public function activer(Request $request)
    {
        $data = $request->validate([
            'retour' => ['required', 'date'],
            'message' => ['nullable', 'string', 'max:140'],
        ], ['retour.required' => 'Choisissez votre date de retour.']);

        $retour = Carbon::parse($data['retour'])->startOfDay();
        if ($erreur = Vacances::erreurDate($retour)) {
            return back()->withErrors(['retour' => $erreur])->withInput();
        }

        $n = Vacances::activer($request->user(), $retour, $data['message'] ?? null);

        return redirect()->route('account.dashboard')
            ->with('status', '🌴 Bonnes vacances ! ' . $n . ' annonce' . ($n > 1 ? 's' : '') . ' en pause jusqu’au ' . Vacances::retour($request->user()) . '.');
    }

    public function terminer(Request $request)
    {
        $n = Vacances::terminer($request->user());

        return redirect()->route('account.dashboard')
            ->with('status', 'Bon retour ! ' . $n . ' annonce' . ($n > 1 ? 's' : '') . ' de nouveau en ligne.');
    }
}

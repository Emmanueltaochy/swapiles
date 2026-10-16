<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Lots;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Le vendeur choisit sa remise pour les lots (« −10 % dès 3 articles »). */
class RemiseLotController extends Controller
{
    public function edit(Request $request)
    {
        return view('account.remise-lot', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'lot_remise_pct' => ['required', 'integer', Rule::in(Lots::REMISES)],
            'lot_remise_min' => ['required', 'integer', Rule::in(Lots::MINIMUMS)],
        ]);

        $request->user()->forceFill($data)->save();

        return redirect()->route('account.settings')->with('status', $data['lot_remise_pct'] > 0
            ? '🎁 Remise lot enregistrée : −' . $data['lot_remise_pct'] . ' % dès ' . $data['lot_remise_min'] . ' articles.'
            : 'Remise lot désactivée.');
    }

    /** « Activer sur mes annonces » : paiement en ligne sur toutes les annonces vendables. */
    public function activerPaiementEnLigne(Request $request)
    {
        $user = $request->user();

        // Sans KYC différé, il faut d'abord un compte de paiement opérationnel.
        if (! config('features.defer_kyc') && ! ($user->stripe_charges_enabled && $user->stripe_payouts_enabled)) {
            return redirect()->route('stripe.connect.activate');
        }

        $n = \App\Support\IncitationLot::activerPaiementEnLigne($user);

        return back()->with('status', $n > 0
            ? '✅ Paiement en ligne activé sur ' . $n . ' annonce' . ($n > 1 ? 's' : '') . ' : vos acheteurs peuvent maintenant les prendre en lot.'
            : 'Le paiement en ligne est déjà activé sur vos annonces.');
    }
}

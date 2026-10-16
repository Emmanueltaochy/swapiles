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
}

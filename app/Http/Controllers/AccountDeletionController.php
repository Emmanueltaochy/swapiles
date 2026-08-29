<?php

namespace App\Http\Controllers;

use App\Support\AdminEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Suppression de compte à la demande de l'utilisateur (RGPD + exigence des
 * stores Apple/Google). La page publique sert d'URL de suppression déclarée
 * sur la fiche Play Store.
 */
class AccountDeletionController extends Controller
{
    /** Page publique décrivant la procédure (URL déclarée sur les stores). */
    public function show(): View
    {
        return view('account-deletion');
    }

    /** Suppression effective (utilisateur connecté, confirmée par mot de passe). */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'confirmation' => ['required', 'in:SUPPRIMER'],
            'password' => ['required', 'string'],
        ], [
            'confirmation.required' => 'Tapez SUPPRIMER pour confirmer.',
            'confirmation.in' => 'Tapez exactement SUPPRIMER (en majuscules) pour confirmer.',
            'password.required' => 'Saisissez votre mot de passe pour confirmer.',
        ]);

        // Les comptes créés par lien magique n'ont pas forcément de mot de passe :
        // dans ce cas on se contente de la confirmation textuelle.
        if (filled($user->password) && ! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Mot de passe incorrect.']);
        }

        $name = $user->name;
        $id = $user->id;

        $outcome = $user->deleteOrAnonymize();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        try {
            AdminEvent::notify(
                'Compte supprimé par le membre',
                'Le membre ' . $name . ' (#' . $id . ') a demandé la suppression de son compte. '
                    . ($outcome === 'anonymized'
                        ? 'Compte ANONYMISÉ (transactions conservées pour la comptabilité).'
                        : 'Compte SUPPRIMÉ intégralement.'),
                null,
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('home')->with('status', 'Votre compte a bien été supprimé. À bientôt sur Swap’Îles !');
    }
}

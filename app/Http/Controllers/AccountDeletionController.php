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

    /**
     * Enregistre le motif de départ, sans aucune donnée personnelle : ni
     * identifiant, ni nom, ni e-mail. Uniquement le motif, la date, l'ancienneté
     * du compte et le fait qu'il y ait eu des ventes.
     */
    private function consignerMotif(Request $request, $user): void
    {
        // On consigne TOUJOURS le départ, même sans réponse : sinon le compteur
        // affiche zéro alors que des membres sont réellement partis.
        $motif = $request->input('reason')
            ?: \App\Models\AccountDeletionReason::NON_RENSEIGNE;

        try {
            \App\Models\AccountDeletionReason::create([
                'reason' => (string) $motif,
                'details' => $request->input('reason_details') ?: null,
                'days_since_signup' => $user->created_at ? (int) $user->created_at->diffInDays(now()) : null,
                'had_sales' => $user->sales()->exists() || $user->purchases()->exists(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Un motif non enregistré ne doit jamais empêcher une suppression.
            report($e);
        }
    }

    /** Suppression effective (utilisateur connecté, confirmée par mot de passe). */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $request->validate([
            'confirmation' => ['required', 'in:SUPPRIMER'],
            'password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'in:' . implode(',', array_keys(\App\Models\AccountDeletionReason::MOTIFS))],
            'reason_details' => ['nullable', 'string', 'max:500'],
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

        // Motif du départ, conservé de façon ANONYME (aucun lien vers le membre).
        // Sans ça, on ne savait pas pourquoi les membres partaient.
        $this->consignerMotif($request, $user);

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
                'account_deleted'
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('home')->with('status', 'Votre compte a bien été supprimé. À bientôt sur Swap’Îles !');
    }
}

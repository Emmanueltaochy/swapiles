<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class TransactionDetailController extends Controller
{
    public function show(Transaction $transaction)
    {
        abort_unless(
            $transaction->buyer_id === Auth::id() || $transaction->seller_id === Auth::id(),
            403
        );

        $transaction->load(['listing.images', 'buyer', 'seller', 'relayPoint']);

        // Lot : on affiche toujours la commande entière depuis sa vente principale.
        if ($transaction->lot_id && ! $transaction->estPrincipale()) {
            return redirect()->route('account.transactions.show', $transaction->transactionsDuLot()->first());
        }
        $lotVentes = $transaction->lot_id ? $transaction->transactionsDuLot()->load('listing.images') : null;

        // Avis mutuels : l'autre partie, mon avis éventuel, et l'avis reçu.
        $me = Auth::id();
        $otherParty = $transaction->seller_id === $me ? $transaction->buyer : $transaction->seller;
        $myReview = \App\Models\Review::where('transaction_id', $transaction->id)
            ->where('reviewer_id', $me)->first();
        $reviewAboutMe = \App\Models\Review::where('transaction_id', $transaction->id)
            ->where('reviewed_id', $me)->first();
        $canReview = $transaction->status === 'completed' && $otherParty !== null
            && $transaction->acheteur_conteste_at === null;

        return view('account.transactions.show', compact(
            'transaction', 'otherParty', 'myReview', 'reviewAboutMe', 'canReview', 'lotVentes'
        ));
    }

    /** « Oui, j'ai bien acheté cet article » (vente en main propre déclarée). */
    public function confirmerAchat(Transaction $transaction)
    {
        abort_unless($transaction->buyer_id === Auth::id() && $transaction->attendReponseAcheteur(), 403);

        $transaction->forceFill(['acheteur_confirme_at' => now()])->save();

        return back()->with('status', 'Merci ! L’achat est confirmé. Vous pouvez maintenant laisser un avis au vendeur.');
    }

    /** « Ce n'est pas moi » : le membre n'est plus rattaché comme acheteur. */
    public function contesterAchat(Transaction $transaction)
    {
        abort_unless($transaction->buyer_id === Auth::id() && $transaction->attendReponseAcheteur(), 403);

        $transaction->forceFill(['acheteur_conteste_at' => now()])->save();

        return redirect()->route('account.transactions.index')
            ->with('status', 'C’est noté : cet achat ne vous est plus attribué. Merci de nous l’avoir signalé.');
    }
}

<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Avis mutuels acheteur ↔ vendeur après une transaction terminée (CB ou
 * espèces). Un seul avis par personne et par transaction.
 */
class ReviewController extends Controller
{
    public function store(Request $request, Transaction $transaction)
    {
        $me = Auth::id();

        // Seuls les deux participants d'une transaction TERMINÉE peuvent noter.
        abort_unless($transaction->buyer_id === $me || $transaction->seller_id === $me, 403);
        abort_unless($transaction->status === 'completed', 403);

        // La personne notée est l'autre partie de la transaction.
        $reviewedId = $transaction->seller_id === $me ? $transaction->buyer_id : $transaction->seller_id;
        if (! $reviewedId) {
            return back()->withErrors([
                'rating' => "L'autre partie n'est pas identifiée pour cette vente : impossible de laisser un avis.",
            ]);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'rating.required' => 'Choisis une note de 1 à 5 étoiles.',
            'rating.min' => 'La note va de 1 à 5 étoiles.',
            'rating.max' => 'La note va de 1 à 5 étoiles.',
        ]);

        // Anti-doublon : un seul avis par auteur et par transaction.
        $already = Review::where('transaction_id', $transaction->id)
            ->where('reviewer_id', $me)
            ->exists();
        if ($already) {
            return back()->with('status', 'Tu as déjà laissé un avis pour cette transaction.');
        }

        Review::create([
            'transaction_id' => $transaction->id,
            'reviewer_id' => $me,
            'reviewed_id' => $reviewedId,
            'rating' => (int) $data['rating'],
            'comment' => $data['comment'] ?? null,
        ]);

        optional(User::find($reviewedId))->recomputeRating();

        return back()->with('status', 'Merci ! Ton avis a bien été enregistré.');
    }
}

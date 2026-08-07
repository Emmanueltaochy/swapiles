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

        $transaction->load(['listing.images', 'buyer', 'seller']);

        // Avis mutuels : l'autre partie, mon avis éventuel, et l'avis reçu.
        $me = Auth::id();
        $otherParty = $transaction->seller_id === $me ? $transaction->buyer : $transaction->seller;
        $myReview = \App\Models\Review::where('transaction_id', $transaction->id)
            ->where('reviewer_id', $me)->first();
        $reviewAboutMe = \App\Models\Review::where('transaction_id', $transaction->id)
            ->where('reviewed_id', $me)->first();
        $canReview = $transaction->status === 'completed' && $otherParty !== null;

        return view('account.transactions.show', compact(
            'transaction', 'otherParty', 'myReview', 'reviewAboutMe', 'canReview'
        ));
    }
}

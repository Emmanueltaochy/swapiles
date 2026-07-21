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

        return view('account.transactions.show', compact('transaction'));
    }
}

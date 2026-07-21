<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function index()
    {
        $purchases = Transaction::with(['listing.images', 'seller'])
            ->where('buyer_id', Auth::id())
            ->latest()
            ->get();

        $sales = Transaction::with(['listing.images', 'buyer'])
            ->where('seller_id', Auth::id())
            ->latest()
            ->get();

        return view('account.transactions.index', compact('purchases', 'sales'));
    }
}

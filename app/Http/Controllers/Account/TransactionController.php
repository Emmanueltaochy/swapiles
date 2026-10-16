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

        return view('account.transactions.index', [
            'purchases' => self::regrouperLots($purchases),
            'sales' => self::regrouperLots($sales),
        ]);
    }

    /**
     * Un lot s'affiche comme UNE commande : on ne garde que sa vente
     * principale, avec le nombre d'articles et le total du lot.
     */
    public static function regrouperLots($transactions)
    {
        $parLot = $transactions->whereNotNull('lot_id')->groupBy('lot_id');

        return $transactions
            ->filter(fn ($t) => ! $t->lot_id || $t->id === $parLot[$t->lot_id]->min('id'))
            ->each(function ($t) use ($parLot) {
                if ($t->lot_id) {
                    $t->setAttribute('lot_nb', $parLot[$t->lot_id]->count());
                    $t->setAttribute('lot_total', (float) $parLot[$t->lot_id]->sum(fn ($v) => (float) $v->amount));
                }
            })
            ->values();
    }
}

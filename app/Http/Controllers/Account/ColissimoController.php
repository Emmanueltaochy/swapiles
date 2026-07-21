<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\ColissimoService;
use Illuminate\Support\Facades\Storage;

class ColissimoController extends Controller
{
    public function generate(Transaction $transaction, ColissimoService $colissimo)
    {
        abort_unless($transaction->seller_id === auth()->id(), 403);

        $result = $colissimo->generateLabel($transaction);

        $transaction->update([
            'carrier' => 'Colissimo',
            'tracking_number' => $result['tracking_number'],
            'tracking_url' => $result['tracking_number']
                ? 'https://www.laposte.fr/outils/suivre-vos-envois?code=' . $result['tracking_number']
                : null,
        ]);

        session()->flash('status', 'Étiquette Colissimo générée.');

        return back();
    }

    public function download(Transaction $transaction)
    {
        abort_unless($transaction->seller_id === auth()->id(), 403);

        $path = 'colissimo/labels/transaction-' . $transaction->id . '.pdf';

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, 'etiquette-colissimo-' . $transaction->id . '.pdf');
    }
}

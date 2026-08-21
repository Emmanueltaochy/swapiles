<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Jobs\SendTransactionStatusEmails;
use App\Models\Transaction;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionWorkflowController extends Controller
{
    public function shipped(Request $request, Transaction $transaction)
    {
        abort_unless($transaction->seller_id === Auth::id(), 403);
        abort_unless(in_array($transaction->status, ['paid', 'pending']), 403);

        $data = $request->validate([
            'carrier' => ['nullable', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
        ]);

        $carrier = $data['carrier'] ?? null;
        $trackingNumber = $data['tracking_number'] ?? null;

        $trackingUrl = null;

        if ($trackingNumber) {
            $trackingUrl = match ($carrier) {
                'Colissimo' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=' . urlencode($trackingNumber),
                'Chronopost' => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT=' . urlencode($trackingNumber),
                default => null,
            };
        }

        $transaction->update([
            'shipping_status' => 'shipped',
            'carrier' => $carrier,
            'tracking_number' => $trackingNumber,
            'tracking_url' => $trackingUrl,
            'shipped_at' => now(),
        ]);

        try {
            SendTransactionStatusEmails::dispatch($transaction->id, 'shipped');
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', 'Article marqué comme expédié.');
    }

    /**
     * Point relais : le vendeur confirme avoir déposé le colis chez le
     * commerçant. L'acheteur est alors invité à venir le retirer avec son code.
     */
    public function relayDeposited(Transaction $transaction)
    {
        abort_unless($transaction->seller_id === Auth::id(), 403);
        abort_unless($transaction->delivery_method === 'relay', 403);
        abort_unless(in_array($transaction->status, ['paid', 'pending']), 403);

        $transaction->update([
            'relay_status' => 'deposited',
            'relay_deposited_at' => now(),
            'shipping_status' => 'shipped',
            'shipped_at' => $transaction->shipped_at ?: now(),
        ]);

        try {
            SendTransactionStatusEmails::dispatch($transaction->id, 'shipped');
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', 'Dépôt confirmé. L’acheteur peut venir retirer son colis au point relais.');
    }

    public function received(Transaction $transaction)
    {
        abort_unless($transaction->buyer_id === Auth::id(), 403);

        $update = [
            'shipping_status' => 'received',
            'received_at' => now(),
            'delivered_at' => now(),
            'status' => 'completed',
            'completed_at' => now(),
            'wallet_status' => 'processing',
        ];

        // Point relais : la confirmation de réception vaut retrait au comptoir.
        if ($transaction->delivery_method === 'relay') {
            $update['relay_status'] = 'collected';
            $update['relay_collected_at'] = now();
        }

        $transaction->update($update);

        try {
            SendTransactionStatusEmails::dispatch($transaction->id, 'received');
        } catch (\Throwable $e) {
            report($e);
        }

        \App\Support\SellerPayout::release($transaction);

        return back()->with('status', 'Transaction finalisée. Le paiement vendeur sera versé si son compte est configuré.');
    }
}

<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stripe\StripeClient;

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
            $transaction->buyer?->notify(new TransactionShippedNotification($transaction));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', 'Article marqué comme expédié.');
    }

    public function received(Transaction $transaction)
    {
        abort_unless($transaction->buyer_id === Auth::id(), 403);

        $transaction->update([
            'shipping_status' => 'received',
            'received_at' => now(),
            'delivered_at' => now(),
            'status' => 'completed',
            'completed_at' => now(),
            'wallet_status' => 'processing',
        ]);

        $this->releaseSellerPayout($transaction);

        return back()->with('status', 'Transaction finalisée. Le paiement vendeur sera versé si son compte est configuré.');
    }

    private function releaseSellerPayout(Transaction $transaction): void
    {
        $transaction->refresh();

        if ($transaction->stripe_transfer_id || $transaction->released_at) {
            return;
        }

        $seller = $transaction->seller;

        if (!$seller || !$seller->stripe_account_id || !$seller->stripe_payouts_enabled) {
            return;
        }

        $sellerAmount = $transaction->seller_amount > 0 ? $transaction->seller_amount : max(0, $transaction->amount - $transaction->commission);

        if ($sellerAmount <= 0) {
            return;
        }

        try {
            $stripe = new StripeClient(env('STRIPE_SECRET'));

            $transfer = $stripe->transfers->create([
                'amount' => $sellerAmount * 100,
                'currency' => 'eur',
                'destination' => $seller->stripe_account_id,
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'listing_id' => $transaction->listing_id,
                    'seller_id' => $transaction->seller_id,
                    'buyer_id' => $transaction->buyer_id,
                    'platform_commission_eur' => $transaction->commission,
                ],
            ]);

            $transaction->update([
                'stripe_transfer_id' => $transfer->id,
                'released_at' => now(),
                'wallet_status' => 'paid',
                'transfer_started_at' => now(),
                'transferred_at' => now(),
                'estimated_payout_date' => now()->addDays(2),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

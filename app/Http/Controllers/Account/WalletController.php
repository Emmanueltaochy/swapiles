<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Transaction;

class WalletController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $sales = Transaction::with(['listing.images', 'buyer'])
            ->where('seller_id', $user->id)
            ->whereIn('status', ['paid', 'completed'])
            ->latest()
            ->get()
            ->filter(fn ($transaction) => $transaction->listing !== null)
            ->values();

        $net = function ($transaction) {
            if ((float) $transaction->seller_amount > 0) {
                return (float) $transaction->seller_amount;
            }

            return max(0,
                (float) $transaction->amount
                - (float) $transaction->commission
                - (float) $transaction->buyer_protection_fee
                - (float) $transaction->shipping_fee
            );
        };

        $pendingAmount = $sales
            ->filter(fn ($transaction) => $transaction->status === 'paid')
            ->sum($net);

        $processingAmount = $sales
            ->filter(fn ($transaction) =>
                $transaction->status === 'completed'
                && empty($transaction->released_at)
            )
            ->sum($net);

        $paidAmount = $sales
            ->filter(fn ($transaction) =>
                $transaction->status === 'completed'
                && !empty($transaction->released_at)
            )
            ->sum($net);

        $stripeReady =
            $user->stripe_account_id
            && $user->stripe_charges_enabled
            && $user->stripe_payouts_enabled
            && $user->stripe_details_submitted;

        if ($stripeReady && !$user->stripe_onboarding_complete) {
            $user->forceFill([
                'stripe_onboarding_complete' => true,
            ])->save();
        }

        return view('account.wallet.index', [
            'sales' => $sales,
            'pendingAmount' => $pendingAmount,
            'processingAmount' => $processingAmount,
            'paidAmount' => $paidAmount,
            'user' => $user,
            'stripeReady' => $stripeReady,
        ]);
    }
}

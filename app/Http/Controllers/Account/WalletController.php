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

        // Compte bancaire lié (4 derniers chiffres) — mis en cache pour ne pas
        // interroger Stripe à chaque affichage.
        $bankInfo = null;
        if ($stripeReady) {
            try {
                $bankInfo = \Illuminate\Support\Facades\Cache::remember(
                    'stripe_bank_' . $user->id,
                    now()->addHours(6),
                    function () use ($user) {
                        $stripe = new \Stripe\StripeClient(env('STRIPE_SECRET'));
                        $ext = $stripe->accounts->allExternalAccounts(
                            $user->stripe_account_id,
                            ['object' => 'bank_account', 'limit' => 1]
                        );
                        $ba = $ext->data[0] ?? null;

                        return $ba ? ['last4' => $ba->last4, 'bank' => $ba->bank_name] : null;
                    }
                );
            } catch (\Throwable $e) {
                report($e);
                $bankInfo = null;
            }
        }

        return view('account.wallet.index', [
            'sales' => $sales,
            'pendingAmount' => $pendingAmount,
            'processingAmount' => $processingAmount,
            'paidAmount' => $paidAmount,
            'user' => $user,
            'stripeReady' => $stripeReady,
            'bankInfo' => $bankInfo,
        ]);
    }
}

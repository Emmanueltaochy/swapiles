<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use App\Notifications\TransactionPaidNotification;
use App\Notifications\TransactionBuyerPaidNotification;
use App\Support\AdminEvent;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = env('STRIPE_WEBHOOK_SECRET');

        try {
            if ($secret) {
                $event = Webhook::constructEvent($payload, $signature, $secret);
            } else {
                $event = json_decode($payload);
            }
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook invalide', [
                'error' => $e->getMessage(),
            ]);

            return response('Invalid signature', 400);
        }

        switch ($event->type) {

            case 'payment_intent.succeeded':

                $paymentIntent = $event->data->object;

                $transaction = Transaction::where(
                    'stripe_payment_intent_id',
                    $paymentIntent->id
                )->first();

                if ($transaction && $transaction->status !== 'paid') {

                    $transaction->update([
                        'status' => 'paid',
                    ]);

                    if ($transaction->listing) {
                        $transaction->listing->update([
                            'status' => 'sold',
                        ]);
                    }

                    AdminEvent::notify(
                        'Paiement Stripe validé',
                        'Stripe a confirmé le paiement de ' . number_format((float) $transaction->amount, 2, ',', ' ') . ' € pour : ' . ($transaction->listing->title ?? 'Annonce'),
                        route('account.transactions.show', $transaction)
                    );

                    if ($transaction->seller) {

                        Notification::create([
                            'user_id' => $transaction->seller_id,
                            'title' => 'Nouvelle vente 🎉',
                            'message' => 'Votre article a été acheté.',
                            'url' => route('account.transactions.show', $transaction),
                        ]);

                        try {
                            $transaction->seller->notify(
                                new TransactionPaidNotification($transaction)
                            );
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }

                    if ($transaction->buyer) {

                        Notification::create([
                            'user_id' => $transaction->buyer_id,
                            'title' => 'Achat confirmé ✅',
                            'message' => 'Votre paiement a été validé.',
                            'url' => route('account.transactions.show', $transaction),
                        ]);

                        try {
                            $transaction->buyer->notify(
                                new TransactionBuyerPaidNotification($transaction)
                            );
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                }

                break;

            case 'checkout.session.completed':

                $session = $event->data->object;

                $transactionId =
                    $session->metadata->transaction_id ?? null;

                if ($transactionId) {

                    $transaction = Transaction::find($transactionId);

                    if ($transaction && $transaction->status !== 'paid') {

                        $transaction->update([
                            'status' => 'paid',
                            'stripe_payment_intent_id' => $session->payment_intent ?? null,
                        ]);

                        if ($transaction->listing) {
                            $transaction->listing->update([
                                'status' => 'sold',
                            ]);
                        }

                        if ($transaction->seller) {

                            Notification::create([
                                'user_id' => $transaction->seller_id,
                                'title' => 'Nouvelle vente 🎉',
                                'message' => 'Votre article a été acheté.',
                                'url' => route('account.transactions.show', $transaction),
                            ]);

                            try {
                                $transaction->seller->notify(
                                    new TransactionPaidNotification($transaction)
                                );
                            } catch (\Throwable $e) {
                                report($e);
                            }
                        }

                        if ($transaction->buyer) {

                            Notification::create([
                                'user_id' => $transaction->buyer_id,
                                'title' => 'Achat confirmé ✅',
                                'message' => 'Votre paiement a été validé.',
                                'url' => route('account.transactions.show', $transaction),
                            ]);

                            try {
                                $transaction->buyer->notify(
                                    new TransactionBuyerPaidNotification($transaction)
                                );
                            } catch (\Throwable $e) {
                                report($e);
                            }
                        }
                    }
                }

                break;

            case 'payment_intent.payment_failed':

                $paymentIntent = $event->data->object;

                Transaction::where(
                    'stripe_payment_intent_id',
                    $paymentIntent->id
                )->update([
                    'status' => 'cancelled',
                ]);

                break;

            case 'charge.refunded':

                $charge = $event->data->object;

                if (!empty($charge->payment_intent)) {

                    Transaction::where(
                        'stripe_payment_intent_id',
                        $charge->payment_intent
                    )->update([
                        'status' => 'refunded',
                    ]);
                }

                break;
        }

        return response('Webhook reçu', 200);
    }
}

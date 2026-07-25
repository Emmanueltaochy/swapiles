<?php

namespace App\Http\Controllers\Stripe;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;
use App\Support\AdminEvent;
use App\Support\TransactionPayment;

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

                // Transition unique et idempotente (partagée avec la page de
                // succès) : crée notifications + e-mails une seule fois.
                if ($transaction && TransactionPayment::markPaidOnce($transaction)) {
                    $fresh = $transaction->fresh(['listing']);

                    AdminEvent::notify(
                        'Paiement Stripe validé',
                        'Stripe a confirmé le paiement de ' . number_format((float) $fresh->amount, 2, ',', ' ') . ' € pour : ' . ($fresh->listing->title ?? 'Annonce'),
                        route('account.transactions.show', $fresh)
                    );
                }

                break;

            case 'checkout.session.completed':

                $session = $event->data->object;

                $transactionId =
                    $session->metadata->transaction_id ?? null;

                if ($transactionId) {

                    $transaction = Transaction::find($transactionId);

                    if ($transaction && $transaction->status === 'pending') {
                        // On rattache le PaymentIntent avant la transition.
                        if (!empty($session->payment_intent) && empty($transaction->stripe_payment_intent_id)) {
                            $transaction->update([
                                'stripe_payment_intent_id' => $session->payment_intent,
                            ]);
                        }

                        TransactionPayment::markPaidOnce($transaction);
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

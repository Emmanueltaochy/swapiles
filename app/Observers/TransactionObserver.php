<?php

namespace App\Observers;

use App\Jobs\SendSellerPaymentReceivedEmail;
use App\Models\Transaction;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        $this->sendSellerPaidEmailIfNeeded($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        $this->sendSellerPaidEmailIfNeeded($transaction);
    }

    private function sendSellerPaidEmailIfNeeded(Transaction $transaction): void
    {
        if ($transaction->status !== 'paid') {
            return;
        }

        if (! empty($transaction->seller_paid_email_sent_at)) {
            return;
        }

        $transaction->loadMissing(['seller', 'buyer', 'listing']);

        if (! $transaction->seller || ! $transaction->seller->email) {
            return;
        }

        SendSellerPaymentReceivedEmail::dispatch($transaction->id);

        $transaction->forceFill([
            'seller_paid_email_sent_at' => now(),
        ])->saveQuietly();
    }
}

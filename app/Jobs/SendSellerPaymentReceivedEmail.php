<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendSellerPaymentReceivedEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $transactionId)
    {
    }

    public function handle(): void
    {
        $transaction = Transaction::with(['seller', 'buyer', 'listing'])->find($this->transactionId);

        if (! $transaction || ! $transaction->seller || ! $transaction->seller->email) {
            return;
        }

        $listingTitle = $transaction->listing?->title ?? 'votre article';
        $buyerName = $transaction->buyer?->name ?? 'un acheteur';
        $itemAmount = max(
            0,
            (float) $transaction->amount
            - (float) $transaction->buyer_protection_fee
            - (float) $transaction->shipping_fee
        );

        $itemAmountFormatted = number_format($itemAmount, 2, ',', ' ');

        $body = "Bonjour,\n\n";
        $body .= "Bonne nouvelle 🎉\n\n";
        $body .= "Votre article « {$listingTitle} » vient d’être payé par {$buyerName} sur Swap’Îles.\n\n";
        $body .= "Prix de vente de l’article : {$itemAmountFormatted} €

";
        $body .= "Le paiement est sécurisé. Votre paiement sera disponible après confirmation de la remise ou de la réception selon le mode choisi.\n\n";
        $body .= "Vous pouvez maintenant préparer l’article.\n\n";
        $body .= "L’équipe Swap’Îles";

        Mail::raw($body, function ($mail) use ($transaction, $listingTitle) {
            $mail->from('contact@swapiles.com', 'Swap Îles')
                ->to($transaction->seller->email)
                ->subject('Votre article a été payé sur Swap’Îles');
        });
    }
}

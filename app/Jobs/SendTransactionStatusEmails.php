<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendTransactionStatusEmails implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;

    /**
     * @param  string  $event  paid | shipped | received | released
     */
    public function __construct(
        public int $transactionId,
        public string $event
    ) {
    }

    public function handle(): void
    {
        $t = Transaction::with(['buyer', 'seller', 'listing'])->find($this->transactionId);

        if (!$t) {
            return;
        }

        $title = $t->listing?->title ?? 'votre article';
        $amount = number_format((float) $t->amount, 2, ',', ' ');
        $netRaw = (float) $t->seller_amount > 0
            ? (float) $t->seller_amount
            : max(0, (float) $t->amount - (float) $t->commission - (float) $t->buyer_protection_fee - (float) $t->shipping_fee);
        $net = number_format($netRaw, 2, ',', ' ');

        try {
            $url = route('account.transactions.show', $t);
        } catch (\Throwable $e) {
            $url = 'https://swapiles.com';
        }

        $messages = $this->messagesFor($title, $amount, $net);

        if (!empty($messages['buyer']) && $t->buyer?->email) {
            $this->send($t->buyer->email, $messages['buyer'][0], $messages['buyer'][1] . $this->footer($url));
        }

        if (!empty($messages['seller']) && $t->seller?->email) {
            $this->send($t->seller->email, $messages['seller'][0], $messages['seller'][1] . $this->footer($url));
        }
    }

    private function messagesFor(string $title, string $amount, string $net): array
    {
        return match ($this->event) {
            'paid' => [
                'buyer' => [
                    "✅ Paiement confirmé — {$title}",
                    "Bonjour,\n\nVotre paiement de {$amount} € pour « {$title} » est bien confirmé. Le vendeur va préparer votre colis.",
                ],
                'seller' => [
                    "🎉 Vous avez vendu {$title} !",
                    "Bonjour,\n\nBonne nouvelle : « {$title} » vient d'être payé ({$amount} €). Préparez le colis puis générez votre bordereau Colissimo, ou convenez de la remise en main propre.",
                ],
            ],
            'shipped' => [
                'buyer' => [
                    "📦 Votre commande est expédiée — {$title}",
                    "Bonjour,\n\nLe vendeur vient d'expédier « {$title} ». Dès réception, pensez à confirmer la réception depuis vos transactions pour finaliser l'achat.",
                ],
                'seller' => [
                    "📦 Expédition enregistrée — {$title}",
                    "Bonjour,\n\nVous avez marqué « {$title} » comme expédié. L'acheteur vient d'en être informé.",
                ],
            ],
            'received' => [
                'buyer' => [
                    "✅ Réception confirmée — {$title}",
                    "Bonjour,\n\nVous avez confirmé la réception de « {$title} ». Merci de votre confiance et à bientôt sur Swap'Îles !",
                ],
                'seller' => [
                    "💶 Réception confirmée — {$title}",
                    "Bonjour,\n\nL'acheteur a confirmé la réception de « {$title} ». Votre paiement va être versé sur votre compte bancaire.",
                ],
            ],
            'released' => [
                'seller' => [
                    "💶 Paiement envoyé — {$title}",
                    "Bonjour,\n\nVotre paiement de {$net} € pour la vente de « {$title} » a été envoyé vers votre compte bancaire (délai habituel : 1 à 3 jours ouvrés).",
                ],
            ],
            default => [],
        };
    }

    private function footer(string $url): string
    {
        return "\n\nVoir la transaction : {$url}\n\nL'équipe Swap'Îles\nhttps://swapiles.com";
    }

    private function send(string $to, string $subject, string $body): void
    {
        Mail::raw($body, function ($mail) use ($to, $subject) {
            $mail->from('contact@swapiles.com', "Swap'Îles")
                ->to($to)
                ->subject($subject);
        });
    }
}

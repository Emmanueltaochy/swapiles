<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionPaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Transaction $transaction) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Votre article a été acheté 🎉")
            ->greeting("Bonjour 👋")
            ->line("Bonne nouvelle ! Votre article a été acheté sur Swap Îles.")
            ->line("Article : " . $this->transaction->listing->title)
            ->line("Montant : " . number_format($this->transaction->amount, 2, ',', ' ') . " €")
            ->action("Voir la transaction", route('account.transactions.index'))
            ->salutation("L’équipe Swap Îles");
    }
}

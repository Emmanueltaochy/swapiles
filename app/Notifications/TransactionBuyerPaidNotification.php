<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionBuyerPaidNotification extends Notification
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
            ->subject("Paiement confirmé ✅")
            ->greeting("Bonjour 👋")
            ->line("Votre paiement a bien été confirmé.")
            ->line("Article : " . $this->transaction->listing->title)
            ->action("Voir ma transaction", route('account.transactions.index'))
            ->salutation("L’équipe Swap Îles");
    }
}

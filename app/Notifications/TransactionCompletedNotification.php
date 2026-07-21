<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionCompletedNotification extends Notification implements ShouldQueue
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
            ->subject("Transaction terminée 💸")
            ->greeting("Bonjour 👋")
            ->line("L’acheteur a confirmé la réception de l’article.")
            ->line("Les fonds vont être transférés sur votre compte Stripe.")
            ->action("Voir mes ventes", route('account.transactions.index'))
            ->salutation("L’équipe Swap Îles");
    }
}

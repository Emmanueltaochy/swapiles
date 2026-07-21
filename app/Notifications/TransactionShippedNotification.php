<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionShippedNotification extends Notification
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
            ->subject("Votre colis a été expédié 📦")
            ->greeting("Bonjour 👋")
            ->line("Le vendeur a marqué votre commande comme expédiée.")
            ->line("Article : " . $this->transaction->listing->title)
            ->action("Suivre ma transaction", route('account.transactions.index'))
            ->salutation("L’équipe Swap Îles");
    }
}

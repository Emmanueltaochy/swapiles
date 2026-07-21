<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FavoritePriceDropNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Listing $listing,
        public int|float $oldPrice,
        public int|float $newPrice
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bonne nouvelle : un favori a baissé de prix')
            ->greeting('Bonjour 👋')
            ->line('Un article que vous avez ajouté en favori a baissé de prix sur Swap Îles.')
            ->line('Article : ' . $this->listing->title)
            ->line('Ancien prix : ' . number_format($this->oldPrice, 0, ',', ' ') . ' €')
            ->line('Nouveau prix : ' . number_format($this->newPrice, 0, ',', ' ') . ' €')
            ->action('Voir l’annonce', route('listings.show', $this->listing))
            ->salutation("L’équipe Swap Îles");
    }
}

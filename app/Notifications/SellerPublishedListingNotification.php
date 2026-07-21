<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerPublishedListingNotification extends Notification
{
    use Queueable;

    public function __construct(public Listing $listing)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sellerName = $this->listing->user?->name ?? 'Un vendeur que vous suivez';

        return (new MailMessage)
            ->subject($sellerName . ' vient de publier une nouvelle annonce')
            ->greeting('Nouvelle annonce sur Swap Îles')
            ->line($sellerName . ' vient de publier : ' . $this->listing->title)
            ->line('Prix : ' . ($this->listing->price > 0 ? number_format($this->listing->price, 0, ',', ' ') . ' €' : 'Gratuit'))
            ->action('Voir l’annonce', route('listings.show', $this->listing))
            ->line('Vous recevez cet email car vous suivez ce vendeur.');
    }
}

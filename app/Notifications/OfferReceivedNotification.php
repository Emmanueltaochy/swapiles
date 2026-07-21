<?php

namespace App\Notifications;

use App\Models\ListingOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfferReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(public ListingOffer $offer) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->from('contact@swapiles.com', 'Swap Îles')
            ->replyTo('contact@swapiles.com')
            ->subject('Nouvelle offre reçue sur Swap Îles')
            ->greeting('Bonjour 👋')
            ->line(($this->offer->buyer->name ?? 'Un membre') . ' vous propose ' . $this->offer->amount . ' € pour votre annonce :')
            ->line('“' . ($this->offer->listing->title ?? 'Votre annonce') . '”')
            ->action('Voir l’offre', route('account.messages.show', [
                'listing' => $this->offer->listing,
                'user' => $this->offer->buyer,
            ]))
            ->line('Vous pouvez accepter ou refuser cette offre depuis votre messagerie.')
            ->salutation("L’équipe Swap Îles");
    }
}

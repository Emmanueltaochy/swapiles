<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\ListingOffer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfferStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ListingOffer $offer, public string $status) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $accepted = $this->status === 'accepted';

        $mail = (new MailMessage)
            ->from('contact@swapiles.com', 'Swap Îles')
            ->replyTo('contact@swapiles.com')
            ->subject($accepted ? 'Votre offre a été acceptée' : 'Votre offre a été refusée')
            ->greeting('Bonjour 👋')
            ->line($accepted
                ? 'Bonne nouvelle, votre offre de ' . $this->offer->amount . ' € a été acceptée.'
                : 'Votre offre de ' . $this->offer->amount . ' € a été refusée.'
            )
            ->line('Annonce : “' . ($this->offer->listing->title ?? 'Annonce Swap Îles') . '”');

        if ($accepted) {
            $mail->action('Finaliser mon achat', route('checkout.show', [
                'listing' => $this->offer->listing,
                'offer' => $this->offer->id,
            ]));
        } else {
            $mail->action('Retourner sur Swap Îles', route('home'));
        }

        return $mail->salutation("L’équipe Swap Îles");
    }
}

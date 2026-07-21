<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingFavoritedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Listing $listing,
        public User $favoriter
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre annonce a été ajoutée en favori ❤️')
            ->greeting('Bonne nouvelle !')
            ->line($this->favoriter->name . ' a ajouté votre annonce en favori :')
            ->line($this->listing->title)
            ->action('Voir mon annonce', route('listings.show', $this->listing))
            ->line('Continuez à publier régulièrement pour augmenter vos chances de vente.');
    }
}

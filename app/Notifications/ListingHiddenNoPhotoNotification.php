<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ListingHiddenNoPhotoNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject('📸 Votre annonce est masquée : il manque une photo')
            ->greeting('Bonjour ' . ($notifiable->name ?? '') . ',')
            ->line('Votre annonce **« ' . $this->listing->title . ' »** a été temporairement masquée car elle ne contient aucune photo.')
            ->line('Les annonces avec photo attirent bien plus d’acheteurs — c’est le premier critère de confiance sur Swap’Îles.')
            ->line('Ajoutez au moins une photo et votre annonce sera de nouveau visible dans la recherche.')
            ->action('Ajouter une photo et republier', route('account.listings.edit', $this->listing))
            ->line('Merci de faire vivre la communauté Swap’Îles 🌴');
    }
}

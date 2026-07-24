<?php

namespace App\Notifications;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FavoriteReminderNotification extends Notification implements ShouldQueue
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
        $price = $this->listing->price > 0
            ? number_format((float) $this->listing->price, 0, ',', ' ') . ' €'
            : 'Gratuit';

        return (new MailMessage)
            ->subject('N’oubliez pas votre coup de cœur ❤️ ' . $this->listing->title)
            ->greeting('Toujours intéressé(e) ?')
            ->line('Vous avez ajouté cet article à vos favoris sur Swap’Îles il y a quelque temps :')
            ->line('**' . $this->listing->title . '** — ' . $price)
            ->line('Il est encore disponible ! Ne le laissez pas filer, quelqu’un d’autre pourrait craquer avant vous.')
            ->action('Revoir l’article', route('listings.show', $this->listing))
            ->line('Astuce : contactez directement le vendeur pour poser vos questions ou faire une offre.')
            ->salutation('À très vite sur Swap’Îles 🌴');
    }
}

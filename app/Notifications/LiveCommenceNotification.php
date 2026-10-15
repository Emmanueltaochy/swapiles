<?php

namespace App\Notifications;

use App\Models\LiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** E-mail « le live commence » pour les membres qui ont demandé à être prévenus. */
class LiveCommenceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public LiveSession $live) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vendeur = $this->live->vendeur->name ?? 'Le vendeur';

        return (new MailMessage)
            ->subject("🔴 {$vendeur} est en live sur Swap'Îles")
            ->greeting('Bonjour 👋')
            ->line("Le live « {$this->live->titre} » que vous attendiez vient de commencer.")
            ->line('Posez vos questions en direct et achetez les articles présentés avant les autres.')
            ->action('Rejoindre le live', route('live.show', $this->live))
            ->line('Vous recevez cet e-mail car vous avez appuyé sur « Me prévenir ».')
            ->salutation("L’équipe Swap'Îles");
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[Swap Îles Admin] ' . $this->title)
            ->greeting('Événement Swap Îles')
            ->line($this->message);

        if ($this->url) {
            $mail->action('Voir dans Swap Îles', $this->url);
        }

        return $mail->line('Notification automatique administrateur.');
    }
}

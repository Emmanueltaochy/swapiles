<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommunityNewsletterNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $subject,
        public string $message,
        public string $buttonLabel,
        public string $buttonUrl,
        public string $format = 'text',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->from(new Address('contact@swapiles.com', 'Swap Îles'))
            ->subject($this->subject)
            ->greeting('Bonjour ' . trim((string) ($notifiable->name ?? '')));

        if ($this->format === 'html') {
            return $mail
                ->view('emails.community-newsletter-html', [
                    'subject' => $this->subject,
                    'html' => $this->message,
                    'buttonLabel' => $this->buttonLabel,
                    'buttonUrl' => $this->buttonUrl,
                ]);
        }

        foreach (preg_split("/\r\n|\n|\r/", $this->message) as $line) {
            if (trim($line) !== '') {
                $mail->line($line);
            }
        }

        return $mail
            ->action($this->buttonLabel, $this->buttonUrl)
            ->line('Merci de faire partie de la communauté Swap Îles.');
    }
}

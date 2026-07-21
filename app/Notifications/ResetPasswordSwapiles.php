<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordSwapiles extends ResetPassword
{
    public function toMail($notifiable)
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->from('contact@swapiles.com', 'Swap Îles')
            ->replyTo('support@swapiles.com')
            ->subject("Réinitialisation de votre mot de passe Swap Îles")
            ->greeting('Bonjour 👋')
            ->line("Vous recevez cet email car une demande de réinitialisation de mot de passe a été effectuée pour votre compte Swap Îles.")
            ->action('Réinitialiser mon mot de passe', $url)
            ->line("Ce lien expire dans 60 minutes.")
            ->line("Si vous n’êtes pas à l’origine de cette demande, vous pouvez ignorer cet email.")
            ->salutation("L’équipe Swap Îles");
    }
}

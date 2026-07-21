<?php

namespace App\Mail;

use App\Models\LoginToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MagicLoginLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LoginToken $loginToken,
        public bool $migrationMode = false
    ) {
    }

    public function build()
    {
        return $this
            ->from('contact@swapiles.com', 'Swap Îles')
            ->subject($this->migrationMode ? 'Swap Îles évolue : reconnectez-vous à votre compte' : 'Votre lien de connexion Swap Îles')
            ->view('emails.magic-login-link');
    }
}

<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAdminEventEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 90;

    public function __construct(
        public string $title,
        public string $message,
        public ?string $url = null
    ) {}

    public function handle(): void
    {
        $body = "Bonjour,\n\n"
            . $this->message . "\n\n";

        if ($this->url) {
            $body .= "Voir dans Swap Îles : " . $this->url . "\n\n";
        }

        $body .= "Notification admin Swap Îles\nhttps://swapiles.com";

        Mail::raw($body, function ($mail) {
            $mail->from('contact@swapiles.com', 'Swap Îles')
                ->to('cabinet@taochyconsulting.fr')
                ->subject('[Swap Îles] ' . $this->title);
        });
    }
}

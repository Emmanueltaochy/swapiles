<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendMessageReceivedEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(
        public int $messageId,
        public int $recipientId
    ) {
    }

    public function handle(): void
    {
        $message = Message::with(['listing', 'sender'])->find($this->messageId);
        $recipient = User::find($this->recipientId);

        if (!$message || !$recipient || !$recipient->email) {
            return;
        }

        $sender = $message->sender;
        $listing = $message->listing;

        $url = $listing
            ? route('account.messages.show', ['listing' => $listing, 'user' => $sender])
            : route('account.messages.show.general', ['user' => $sender]);

        $subject = 'Nouveau message sur Swap Îles';

        $body = "Bonjour,\n\n"
            . ($sender->name ?? 'Un membre') . " vous a envoyé un nouveau message sur Swap Îles.\n\n"
            . ($listing ? "Annonce : " . $listing->title . "\n\n" : "Conversation directe\n\n")
            . "Message :\n"
            . $message->body . "\n\n"
            . "Voir le message : " . $url . "\n\n"
            . "L'équipe Swap Îles\n"
            . "https://swapiles.com";

        Mail::raw($body, function ($mail) use ($recipient, $subject) {
            $mail->from('contact@swapiles.com', 'Swap Îles')
                ->to($recipient->email)
                ->subject($subject);
        });
    }
}

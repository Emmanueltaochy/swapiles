<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Relance « tu as un message sans réponse » envoyée au destinataire 24 h après
 * un message resté sans réponse (point 8). Même mécanisme fiable (Mail::raw)
 * que les autres e-mails transactionnels.
 */
class SendMessageReminderEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public int $messageId)
    {
    }

    public function handle(): void
    {
        $message = Message::with(['listing', 'sender', 'receiver'])->find($this->messageId);

        if (!$message) {
            return;
        }

        $recipient = $message->receiver;
        if (!$recipient || blank($recipient->email)) {
            return;
        }

        $sender = $message->sender;
        $listing = $message->listing;

        $url = $listing
            ? route('account.messages.show', ['listing' => $listing, 'user' => $sender])
            : route('account.messages.show.general', ['user' => $sender]);

        $subject = '💬 Un acheteur attend ta réponse sur Swap’Îles';

        $body = "Bonjour,\n\n"
            . ($sender->name ?? 'Un membre') . " t'a écrit il y a plus de 24 h et attend toujours ta réponse.\n\n"
            . ($listing ? "Annonce : " . $listing->title . "\n\n" : "Conversation directe\n\n")
            . "Message :\n" . \Illuminate\Support\Str::limit($message->body, 300) . "\n\n"
            . "Réponds vite pour ne pas perdre la vente : " . $url . "\n\n"
            . "L'équipe Swap'Îles\nhttps://swapiles.com";

        Mail::raw($body, function ($mail) use ($recipient, $subject) {
            $mail->from('contact@swapiles.com', "Swap'Îles")
                ->to($recipient->email)
                ->subject($subject);
        });
    }
}

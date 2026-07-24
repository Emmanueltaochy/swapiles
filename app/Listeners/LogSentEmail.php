<?php

namespace App\Listeners;

use App\Models\SentEmail;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Schema;

class LogSentEmail
{
    public function handle(MessageSent $event): void
    {
        try {
            // La table peut ne pas exister pendant les migrations initiales.
            if (! Schema::hasTable('sent_emails')) {
                return;
            }

            /** @var \Symfony\Component\Mime\Email $message */
            $message = $event->message;

            $to = $message->getTo();
            $first = $to[0] ?? null;

            SentEmail::create([
                'to_email' => $first ? $first->getAddress() : null,
                'to_name' => $first && $first->getName() !== '' ? $first->getName() : null,
                'subject' => $message->getSubject(),
                'mailer' => $event->data['mailer'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Ne jamais bloquer l'envoi d'un e-mail à cause du log.
            report($e);
        }
    }
}

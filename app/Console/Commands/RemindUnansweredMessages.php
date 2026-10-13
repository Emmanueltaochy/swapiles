<?php

namespace App\Console\Commands;

use App\Jobs\SendMessageReminderEmail;
use App\Models\Message;
use Illuminate\Console\Command;

/**
 * Point 8 : relance le destinataire d'un message resté SANS RÉPONSE depuis 24 h.
 *
 * « Sans réponse » = c'est le DERNIER message de la conversation (personne n'a
 * écrit après) et il a plus de 24 h. On ne relance qu'une fois (reminder_sent_at).
 * Cas concret : un acheteur écrit au vendeur, le vendeur ne répond pas.
 */
class RemindUnansweredMessages extends Command
{
    protected $signature = 'messages:remind-unanswered
        {--hours=24 : Ancienneté minimale sans réponse}
        {--max-days=14 : Ne pas relancer de très vieux messages au premier passage}
        {--dry-run : Affiche sans envoyer}';

    protected $description = 'Relance le destinataire d\'un message resté sans réponse depuis 24 h';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $olderThan = now()->subHours((int) $this->option('hours'));
        $notBefore = now()->subDays((int) $this->option('max-days'));

        $sent = 0;
        $skipped = 0;

        Message::query()
            ->whereNull('reminder_sent_at')
            ->where('created_at', '<', $olderThan)
            ->where('created_at', '>=', $notBefore)
            ->orderBy('id')
            ->chunkById(500, function ($messages) use (&$sent, &$skipped, $dryRun) {
                foreach ($messages as $m) {
                    // Y a-t-il un message PLUS RÉCENT dans la même conversation ?
                    // (même annonce + même paire d'interlocuteurs, dans un sens
                    // ou l'autre). Si oui, la conversation a avancé -> pas de relance.
                    $laterExists = Message::query()
                        ->where('id', '>', $m->id)
                        ->when($m->listing_id === null,
                            fn ($q) => $q->whereNull('listing_id'),
                            fn ($q) => $q->where('listing_id', $m->listing_id))
                        ->where(function ($q) use ($m) {
                            $q->where(fn ($s) => $s->where('sender_id', $m->sender_id)->where('receiver_id', $m->receiver_id))
                              ->orWhere(fn ($s) => $s->where('sender_id', $m->receiver_id)->where('receiver_id', $m->sender_id));
                        })
                        ->exists();

                    if ($laterExists) {
                        // Conversation poursuivie : on marque pour ne pas ré-scanner.
                        $m->forceFill(['reminder_sent_at' => now()])->save();
                        $skipped++;
                        continue;
                    }

                    // Dernier message de la conversation, sans réponse depuis 24 h.
                    if ($dryRun) {
                        $this->line("→ [dry-run] relance #{$m->id} vers user {$m->receiver_id}");
                    } else {
                        SendMessageReminderEmail::dispatch($m->id);
                        self::notifier($m);
                        $m->forceFill(['reminder_sent_at' => now()])->save();
                    }
                    $sent++;
                }
            });

        $this->info($dryRun
            ? "Relances qui seraient envoyées : {$sent} (ignorées : {$skipped})"
            : "Relances envoyées : {$sent} (ignorées : {$skipped})");

        return self::SUCCESS;
    }

    /** La même relance, dans l'appli. */
    private static function notifier(Message $m): void
    {
        try {
            $expediteur = \App\Models\User::find($m->sender_id);
            $annonce = $m->listing_id ? \App\Models\Listing::find($m->listing_id) : null;

            \App\Models\Notification::create([
                'user_id' => $m->receiver_id,
                'type' => 'message_reminder',
                'title' => '💬 ' . ($expediteur->name ?? 'Un membre') . ' attend ta réponse',
                'message' => $annonce
                    ? 'Message à propos de « ' . $annonce->title . ' », resté sans réponse depuis hier.'
                    : 'Son message est resté sans réponse depuis hier.',
                'url' => $annonce && $expediteur
                    ? route('account.messages.show', ['listing' => $annonce, 'user' => $expediteur], absolute: false)
                    : route('account.messages.index', absolute: false),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

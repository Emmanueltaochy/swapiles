<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\Notification;
use App\Notifications\ListingHiddenNoPhotoNotification;
use Illuminate\Console\Command;

class HidePhotolessListings extends Command
{
    protected $signature = 'listings:hide-photoless
        {--dry-run : Affiche les annonces concernées sans les masquer}';

    protected $description = 'Masque (statut brouillon) les annonces publiées sans aucune photo — elles nuisent à la conversion sur la page recherche. Prévient le vendeur (in-app + e-mail). Réversible et idempotent.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Annonces déjà masquées par nous lors d'un précédent passage AVANT l'ajout
        // de l'e-mail (elles ont reçu la notif in-app mais pas le mail) : on les
        // rattrape pour leur envoyer l'e-mail une seule fois.
        $backfillIds = Notification::query()
            ->where('type', 'listing_needs_photo')
            ->pluck('url')
            ->map(function ($url) {
                return preg_match('#/mes-annonces/(\d+)/#', (string) $url, $m) ? (int) $m[1] : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        // Cible : annonces sans photo, jamais encore traitées (photoless_hidden_at
        // nul), qui sont soit publiées, soit déjà masquées par nous (rattrapage).
        $query = Listing::query()
            ->whereDoesntHave('images')
            ->whereNull('photoless_hidden_at')
            ->where(function ($q) use ($backfillIds) {
                $q->where('status', 'published');
                if (! empty($backfillIds)) {
                    $q->orWhere(function ($qq) use ($backfillIds) {
                        $qq->where('status', 'draft')->whereIn('id', $backfillIds);
                    });
                }
            });

        $total = (clone $query)->count();
        $this->info("Annonces sans photo à traiter : {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $processed = 0;

        $query->with('user')->chunkById(200, function ($listings) use (&$processed, $dryRun) {
            foreach ($listings as $listing) {
                if ($dryRun) {
                    $this->line("→ [dry-run] #{$listing->id} · {$listing->status} · {$listing->title} · " . ($listing->user?->email ?? '—'));
                    $processed++;

                    continue;
                }

                // Masquer si encore publiée (les brouillons rattrapés le sont déjà).
                $updates = ['photoless_hidden_at' => now()];
                if ($listing->status === 'published') {
                    $updates['status'] = 'draft';
                }
                $listing->forceFill($updates)->save();

                if ($listing->user_id) {
                    // Notif in-app (une seule, on ne duplique pas au rattrapage).
                    try {
                        $alreadyNotified = Notification::where('user_id', $listing->user_id)
                            ->where('type', 'listing_needs_photo')
                            ->where('url', 'like', '%/mes-annonces/' . $listing->id . '/%')
                            ->exists();

                        if (! $alreadyNotified) {
                            Notification::create([
                                'user_id' => $listing->user_id,
                                'type' => 'listing_needs_photo',
                                'title' => 'Ajoutez une photo à votre annonce 📸',
                                'message' => 'Votre annonce « ' . $listing->title . ' » a été temporairement masquée car elle n’a pas de photo. Ajoutez une photo pour la remettre en ligne — les annonces avec photo se vendent bien mieux !',
                                'url' => route('account.listings.edit', $listing, absolute: false),
                            ]);
                        }
                    } catch (\Throwable $e) {
                        report($e);
                    }

                    // E-mail au vendeur : « votre annonce est masquée par manque de photo ».
                    try {
                        $listing->user?->notify(new ListingHiddenNoPhotoNotification($listing));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                $processed++;
            }
        });

        $this->info($dryRun
            ? "Total qui seraient traitées : {$processed}"
            : "Annonces traitées (masquées + vendeur prévenu) : {$processed}");

        return self::SUCCESS;
    }
}

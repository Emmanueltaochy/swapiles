<?php

namespace App\Console\Commands;

use App\Models\Listing;
use App\Models\Notification;
use Illuminate\Console\Command;

class HidePhotolessListings extends Command
{
    protected $signature = 'listings:hide-photoless
        {--dry-run : Affiche les annonces concernées sans les masquer}';

    protected $description = 'Masque (statut brouillon) les annonces publiées sans aucune photo — elles nuisent à la conversion sur la page recherche. Réversible : le vendeur ajoute une photo et republie.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Listing::query()
            ->where('status', 'published')
            ->whereDoesntHave('images');

        $total = (clone $query)->count();
        $this->info("Annonces publiées sans photo : {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $hidden = 0;

        $query->with('user')->chunkById(200, function ($listings) use (&$hidden, $dryRun) {
            foreach ($listings as $listing) {
                if ($dryRun) {
                    $this->line("→ [dry-run] #{$listing->id} · {$listing->title} · " . ($listing->user?->email ?? '—'));
                    $hidden++;

                    continue;
                }

                $listing->update(['status' => 'draft']);

                // On prévient le vendeur (notification in-app + e-mail) pour qu'il
                // ajoute une photo et remette son annonce en ligne.
                if ($listing->user_id) {
                    try {
                        Notification::create([
                            'user_id' => $listing->user_id,
                            'type' => 'listing_needs_photo',
                            'title' => 'Ajoutez une photo à votre annonce 📸',
                            'message' => 'Votre annonce « ' . $listing->title . ' » a été temporairement masquée car elle n’a pas de photo. Ajoutez une photo pour la remettre en ligne — les annonces avec photo se vendent bien mieux !',
                            'url' => route('account.listings.edit', $listing, absolute: false),
                        ]);
                    } catch (\Throwable $e) {
                        report($e);
                    }

                    // E-mail au vendeur : « votre annonce est masquée par manque de photo ».
                    try {
                        $listing->user?->notify(new \App\Notifications\ListingHiddenNoPhotoNotification($listing));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                $hidden++;
            }
        });

        $this->info($dryRun
            ? "Total qui seraient masquées : {$hidden}"
            : "Annonces masquées : {$hidden}");

        return self::SUCCESS;
    }
}

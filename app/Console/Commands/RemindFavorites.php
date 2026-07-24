<?php

namespace App\Console\Commands;

use App\Models\Favorite;
use App\Notifications\FavoriteReminderNotification;
use Illuminate\Console\Command;

class RemindFavorites extends Command
{
    protected $signature = 'favorites:remind
        {--days=7 : Ancienneté minimale du favori (en jours) pour déclencher le rappel}
        {--max-days=45 : Ancienneté maximale (évite de relancer de très vieux favoris au premier passage)}
        {--dry-run : Affiche sans envoyer}';

    protected $description = 'Envoie un rappel « N’oubliez pas votre favori » aux membres dont l’article favori (encore disponible) date d’au moins une semaine';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = (int) $this->option('days');
        $maxDays = (int) $this->option('max-days');

        $olderThan = now()->subDays($days);
        $notBefore = now()->subDays($maxDays);

        $query = Favorite::query()
            ->with(['user', 'listing.user'])
            ->whereNull('reminded_at')
            ->where('created_at', '<=', $olderThan)
            ->where('created_at', '>=', $notBefore)
            // L'article doit toujours exister et être en ligne (ni vendu, ni masqué).
            ->whereHas('listing', fn ($q) => $q->where('status', 'published'));

        $total = (clone $query)->count();
        $this->info("Favoris éligibles au rappel : {$total}");

        $sent = 0;
        $skipped = 0;

        $query->chunkById(200, function ($favorites) use (&$sent, &$skipped, $dryRun) {
            foreach ($favorites as $favorite) {
                $user = $favorite->user;
                $listing = $favorite->listing;

                // Sécurités : membre valide, non banni, e-mail présent, et on ne
                // relance pas quelqu'un sur sa propre annonce.
                if (! $user || ! $listing || blank($user->email) || $user->is_banned) {
                    $skipped++;
                    $this->markReminded($favorite, $dryRun);

                    continue;
                }

                if ($listing->user_id === $user->id) {
                    $skipped++;
                    $this->markReminded($favorite, $dryRun);

                    continue;
                }

                if ($dryRun) {
                    $this->line("→ [dry-run] {$user->email} · {$listing->title}");
                    $sent++;

                    continue;
                }

                try {
                    $user->notify(new FavoriteReminderNotification($listing));
                    $this->markReminded($favorite, false);
                    $sent++;
                } catch (\Throwable $e) {
                    report($e);
                    $this->warn("Échec pour favori #{$favorite->id} : {$e->getMessage()}");
                }
            }
        });

        $this->info("Rappels envoyés : {$sent} · ignorés : {$skipped}");

        return self::SUCCESS;
    }

    protected function markReminded(Favorite $favorite, bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        $favorite->forceFill(['reminded_at' => now()])->save();
    }
}

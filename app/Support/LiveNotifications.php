<?php

namespace App\Support;

use App\Models\LiveSession;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\LiveCommenceNotification;
use Illuminate\Support\Collection;

/**
 * Prévenir les spectateurs d'un live :
 *  - 15 min avant (lives programmés) : ceux qui ont appuyé sur « Me prévenir » ;
 *  - au démarrage : ces mêmes membres (notification + e-mail) et les abonnés
 *    du dressing (notification seulement, pour ne pas les inonder d'e-mails).
 *
 * Pendant la phase de test, seuls les membres qui ont accès au live
 * (admins, testeurs) reçoivent quoi que ce soit.
 */
class LiveNotifications
{
    /** Membres inscrits via « Me prévenir », qui ont accès au live. */
    public static function abonnes(LiveSession $live): Collection
    {
        return self::filtrer($live, $live->abonnes()->get());
    }

    /** Abonnés du dressing qui ne se sont pas déjà inscrits au live. */
    public static function suiveurs(LiveSession $live): Collection
    {
        $vendeur = $live->vendeur;
        if (! $vendeur || ! method_exists($vendeur, 'followers')) {
            return collect();
        }

        $dejaInscrits = $live->abonnes()->pluck('users.id')->all();

        return self::filtrer($live, $vendeur->followers()->whereNotIn('users.id', $dejaInscrits)->get());
    }

    private static function filtrer(LiveSession $live, Collection $membres): Collection
    {
        return $membres
            ->reject(fn (User $u) => (int) $u->id === (int) $live->user_id)
            ->reject(fn (User $u) => (bool) ($u->is_banned ?? false))
            ->filter(fn (User $u) => LiveAcces::visible($u))
            ->values();
    }

    private static function lien(LiveSession $live): string
    {
        return route('live.show', $live, absolute: false);
    }

    /** Crée la notification sauf si ce membre l'a déjà reçue pour ce live. */
    private static function notifier(User $u, string $type, string $titre, string $message, string $url): bool
    {
        if (Notification::where('user_id', $u->id)->where('type', $type)->where('url', $url)->exists()) {
            return false;
        }

        Notification::create(['user_id' => $u->id, 'type' => $type, 'title' => $titre, 'message' => $message, 'url' => $url]);

        return true;
    }

    /** « Le live commence dans 15 min ». */
    public static function bientot(LiveSession $live): int
    {
        $vendeur = $live->vendeur->name ?? 'Un vendeur';
        $minutes = max(1, (int) round(now()->diffInMinutes($live->debut_prevu_at)));
        $n = 0;

        foreach (self::abonnes($live) as $u) {
            $n += (int) self::notifier($u, 'live_soon', "⏰ Live dans {$minutes} min",
                "Le live de {$vendeur} « {$live->titre} » commence bientôt. Prépare-toi !", self::lien($live));
        }

        $live->forceFill(['rappel_envoye_at' => now()])->saveQuietly();

        return $n;
    }

    /** « C'est parti ! » au démarrage du live. */
    public static function demarrage(LiveSession $live): int
    {
        $vendeur = $live->vendeur->name ?? 'Un vendeur';
        $url = self::lien($live);
        $n = 0;

        foreach (self::abonnes($live) as $u) {
            if (self::notifier($u, 'live_started', "🔴 {$vendeur} est en live !", "« {$live->titre} » commence maintenant. Viens poser tes questions et faire de bonnes affaires.", $url)) {
                $n++;
                if ($u->email && $u->accepteNotification('live_started', 'email')) {
                    $u->notify(new LiveCommenceNotification($live));
                }
            }
        }

        foreach (self::suiveurs($live) as $u) {
            $n += (int) self::notifier($u, 'live_started_suivi', "🔴 {$vendeur} est en live", "Un dressing que tu suis présente ses articles en direct : « {$live->titre} ».", $url);
        }

        return $n;
    }

    /** Lives programmés qui commencent bientôt et dont le rappel n'est pas parti. */
    public static function envoyerRappels(): int
    {
        $total = 0;

        LiveSession::query()->with('vendeur')
            ->where('statut', 'programme')
            ->whereNull('rappel_envoye_at')
            ->whereBetween('debut_prevu_at', [now(), now()->addMinutes((int) config('live.rappel_minutes', 15) + 1)])
            ->get()
            ->each(function (LiveSession $live) use (&$total) {
                $total += self::bientot($live);
            });

        return $total;
    }
}

<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;

/**
 * Demande de note de l'appli, envoyée à la main depuis l'admin à des
 * membres choisis (vendeurs contents…). Notification dans l'appli + push,
 * qui ouvre directement la page « laisser un avis » du bon store.
 */
class NoteAppli
{
    /** On ne redemande pas avant ce délai. */
    public const DELAI_JOURS = 60;

    public static function lienAvis(string $plateforme): ?string
    {
        return match ($plateforme) {
            'ios' => config('deeplinks.stores.ios') . (str_contains((string) config('deeplinks.stores.ios'), '?') ? '&' : '?') . 'action=write-review',
            'android' => 'https://play.google.com/store/apps/details?id=' . config('deeplinks.android.package', 'com.swapiles.app') . '&showAllReviews=true',
            default => null,
        };
    }

    /** Fenêtre intégrée : pas plus d'une fois tous les 4 mois (Apple limite aussi à 3 par an). */
    public const NATIF_DELAI_JOURS = 120;

    /** Un moment heureux reste « à proposer » pendant 14 jours. */
    public const NATIF_VALIDITE_JOURS = 14;

    /**
     * Moment heureux (avis de 4 ou 5 étoiles laissé ou reçu) : la fenêtre de
     * notation de l'appli s'ouvrira à sa prochaine visite dans l'appli.
     * On ne demande jamais « vous aimez l'appli ? » avant : Apple et Google
     * interdisent de trier les membres contents.
     */
    public static function momentHeureux(?User $membre): void
    {
        if (! $membre || $membre->is_banned) {
            return;
        }
        if ($membre->note_native_at && $membre->note_native_at->gt(now()->subDays(self::NATIF_DELAI_JOURS))) {
            return;
        }

        $membre->forceFill(['note_native_due_at' => now()])->saveQuietly();
    }

    /** La fenêtre doit-elle s'ouvrir pour ce membre (dans l'appli) ? */
    public static function natifDu(?User $membre): bool
    {
        return $membre
            && $membre->note_native_due_at
            && $membre->note_native_due_at->gt(now()->subDays(self::NATIF_VALIDITE_JOURS))
            && ! ($membre->note_native_at && $membre->note_native_at->gt(now()->subDays(self::NATIF_DELAI_JOURS)));
    }

    /** La fenêtre vient d'être proposée. */
    public static function natifPropose(User $membre): void
    {
        $membre->forceFill(['note_native_at' => now(), 'note_native_due_at' => null])->saveQuietly();
    }

    /** Envoie la demande ; false si le membre est banni ou a déjà été sollicité récemment. */
    public static function demander(User $membre): bool
    {
        if ($membre->is_banned || ($membre->note_demandee_at && $membre->note_demandee_at->gt(now()->subDays(self::DELAI_JOURS)))) {
            return false;
        }

        $prenom = trim(explode(' ', (string) $membre->name)[0]) ?: 'Bonjour';

        Notification::create([
            'user_id' => $membre->id,
            'type' => 'app_note',
            'title' => 'Vous aimez Swap’Îles ? ⭐',
            'message' => $prenom . ', une note sur l’App Store ou Google Play nous aide énormément à faire connaître l’appli dans les îles. Ça prend 30 secondes, merci 🙏',
            'url' => '/noter',
        ]);

        $membre->forceFill(['note_demandee_at' => now()])->save();

        return true;
    }
}

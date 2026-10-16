<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Mode vacances : le vendeur met son dressing en pause jusqu'à une date de
 * retour. Ses annonces en ligne passent au statut « vacances » (invisibles
 * partout où l'on ne montre que les annonces en ligne, et impossibles à
 * acheter), puis reviennent toutes seules le jour du retour.
 */
class Vacances
{
    public const STATUT = 'vacances';

    public const JOURS_MAX = 60;

    public static function enVacances(?User $user): bool
    {
        return $user !== null
            && $user->vacances_jusqu_au !== null
            && Carbon::parse($user->vacances_jusqu_au)->startOfDay()->gt(today());
    }

    /** « De retour le mardi 12 novembre » */
    public static function retour(User $user): string
    {
        return Carbon::parse($user->vacances_jusqu_au)->locale('fr')->translatedFormat('l j F');
    }

    /** Message d'erreur si la date n'est pas acceptable, sinon null. */
    public static function erreurDate(?Carbon $retour): ?string
    {
        return match (true) {
            $retour === null => 'Choisissez votre date de retour.',
            $retour->lte(today()) => 'La date de retour doit être après aujourd’hui.',
            $retour->gt(today()->addDays(self::JOURS_MAX)) => 'Le mode vacances dure ' . self::JOURS_MAX . ' jours au maximum.',
            default => null,
        };
    }

    public static function activer(User $user, Carbon $retour, ?string $message): int
    {
        $user->forceFill([
            'vacances_jusqu_au' => $retour->toDateString(),
            'vacances_message' => filled($message) ? mb_substr(trim($message), 0, 140) : null,
        ])->save();

        return Listing::where('user_id', $user->id)->where('status', 'published')->update(['status' => self::STATUT]);
    }

    public static function terminer(User $user): int
    {
        $user->forceFill(['vacances_jusqu_au' => null, 'vacances_message' => null])->save();

        return Listing::where('user_id', $user->id)->where('status', self::STATUT)->update(['status' => 'published']);
    }

    /** Chaque heure : le jour du retour, le dressing rouvre tout seul. */
    public static function terminerLesRetours(): int
    {
        $n = 0;
        User::query()
            ->whereNotNull('vacances_jusqu_au')
            ->whereDate('vacances_jusqu_au', '<=', today())
            ->each(function (User $user) use (&$n) {
                self::terminer($user);
                $n++;
            });

        return $n;
    }
}

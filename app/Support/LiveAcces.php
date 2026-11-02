<?php

namespace App\Support;

use App\Models\User;

/**
 * Qui peut REGARDER un live, et qui peut en LANCER un.
 *
 *  - Regarder : tout le monde quand LIVE_OUVERT est activé ; sinon seulement
 *    ceux qui peuvent animer (phase de test).
 *  - Lancer un live : admins, testeurs (LIVE_TESTEURS) et membres autorisés
 *    depuis l'admin (« Compte pro / lives »). Ouvrir les lives au public ne
 *    permet donc pas à n'importe qui d'en lancer.
 */
class LiveAcces
{
    public static function visible(?User $user): bool
    {
        return config('live.ouvert_a_tous') || self::peutAnimer($user);
    }

    public static function peutAnimer(?User $user): bool
    {
        if (! $user || $user->is_banned) {
            return false;
        }

        return $user->isAdmin()
            || (bool) $user->live_autorise
            || in_array(mb_strtolower($user->email), array_map('mb_strtolower', config('live.testeurs', [])), true);
    }
}

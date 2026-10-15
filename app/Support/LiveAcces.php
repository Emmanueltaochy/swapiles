<?php

namespace App\Support;

use App\Models\User;

/** Qui peut voir la vente en direct pendant la phase de test. */
class LiveAcces
{
    public static function visible(?User $user): bool
    {
        if (config('live.ouvert_a_tous')) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || in_array(mb_strtolower($user->email), array_map('mb_strtolower', config('live.testeurs', [])), true);
    }
}

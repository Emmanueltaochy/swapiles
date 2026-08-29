<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Blocage / déblocage d'un membre. Un membre bloqué ne peut plus échanger de
 * messages avec l'utilisateur (dans les deux sens).
 */
class BlockController extends Controller
{
    public function toggle(User $user): RedirectResponse
    {
        $me = Auth::user();

        if ((int) $user->id === (int) $me->id) {
            return back()->with('error', 'Vous ne pouvez pas vous bloquer vous-même.');
        }

        if ($me->hasBlocked($user)) {
            $me->blockedUsers()->detach($user->id);

            return back()->with('block_status', ['blocked' => false, 'name' => $user->name]);
        }

        $me->blockedUsers()->syncWithoutDetaching([$user->id]);

        return back()->with('block_status', ['blocked' => true, 'name' => $user->name]);
    }
}

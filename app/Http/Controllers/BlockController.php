<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Blocage / déblocage d'un membre. Un membre bloqué ne peut plus échanger de
 * messages avec l'utilisateur (dans les deux sens).
 */
class BlockController extends Controller
{
    public function toggle(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $me = Auth::user();

        if ((int) $user->id === (int) $me->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Vous ne pouvez pas vous bloquer vous-même.',
                ], 422);
            }

            return back()->with('error', 'Vous ne pouvez pas vous bloquer vous-même.');
        }

        if ($me->hasBlocked($user)) {
            $me->blockedUsers()->detach($user->id);
            $bloque = false;
        } else {
            $me->blockedUsers()->syncWithoutDetaching([$user->id]);
            $bloque = true;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'blocked' => $bloque,
                'name' => $user->name,
                'message' => $bloque
                    ? $user->name . ' est bloqué. Vous ne recevrez plus ses messages.'
                    : $user->name . ' est débloqué.',
            ]);
        }

        return back()->with('block_status', ['blocked' => $bloque, 'name' => $user->name]);
    }
}

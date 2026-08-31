<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Enregistrement du jeton d'appareil (envoyé par l'app mobile au chargement,
 * une fois la permission de notification accordée).
 */
class PushController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:android,ios,web'],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => Auth::id(),
                'platform' => $data['platform'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }
}

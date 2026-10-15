<?php

namespace App\Support;

use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Accès au service vidéo LiveKit : un « jeton » signé par le serveur dit
 * qui entre dans quelle salle, et s'il peut filmer (le vendeur) ou seulement
 * regarder (les spectateurs). La clé secrète ne quitte jamais le serveur.
 */
class LiveKit
{
    public static function configure(): bool
    {
        return filled(config('live.livekit.url')) && filled(config('live.livekit.key')) && filled(config('live.livekit.secret'));
    }

    public static function salle(LiveSession $live): string
    {
        return 'swapiles-live-' . $live->id;
    }

    /** Jeton d'accès (JWT signé HS256), valable 6 heures. */
    public static function jeton(LiveSession $live, ?User $user, bool $publier, ?string $identite = null): string
    {
        $maintenant = time();
        $identite ??= $user ? 'membre-' . $user->id : 'invite-' . Str::random(12);

        $contenu = [
            'iss' => (string) config('live.livekit.key'),
            'sub' => $identite,
            'name' => $user?->name ?? 'Spectateur',
            'nbf' => $maintenant - 10,
            'exp' => $maintenant + 6 * 3600,
            'video' => [
                'room' => self::salle($live),
                'roomJoin' => true,
                'canSubscribe' => true,
                'canPublish' => $publier,
                'canPublishData' => false,
                // Les spectateurs n'apparaissent pas comme participants.
                'hidden' => ! $publier,
            ],
        ];

        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $entete = $b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $corps = $b64(json_encode($contenu));
        $signature = $b64(hash_hmac('sha256', $entete . '.' . $corps, (string) config('live.livekit.secret'), true));

        return $entete . '.' . $corps . '.' . $signature;
    }
}

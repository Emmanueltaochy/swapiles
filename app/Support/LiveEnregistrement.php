<?php

namespace App\Support;

use App\Models\LiveSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Enregistrement d'un live (pour le replay).
 *
 * LiveKit filme la salle comme un spectateur invisible et dépose la vidéo
 * (MP4, format portrait) dans notre espace de stockage compatible S3. Rien
 * ne transite par notre serveur. Sans stockage configuré, rien n'est
 * enregistré et le live fonctionne normalement.
 */
class LiveEnregistrement
{
    public static function configure(): bool
    {
        $r = config('live.replay');

        return LiveKit::configure()
            && filled($r['cle'] ?? null) && filled($r['secret'] ?? null)
            && filled($r['bucket'] ?? null) && filled($r['url_publique'] ?? null);
    }

    private static function appeler(string $methode, array $corps): ?array
    {
        $reponse = Http::withToken(LiveKit::jetonServeur(['roomRecord' => true]))
            ->acceptJson()->timeout(15)
            ->post(LiveKit::api() . '/twirp/livekit.Egress/' . $methode, $corps);

        if (! $reponse->successful()) {
            Log::warning('Live : échec ' . $methode, ['statut' => $reponse->status(), 'reponse' => mb_substr($reponse->body(), 0, 500)]);

            return null;
        }

        return $reponse->json();
    }

    /** Lance l'enregistrement (une seule fois par live). */
    public static function demarrer(LiveSession $live): bool
    {
        if (! self::configure() || ! $live->enDirect()) {
            return false;
        }
        if ($live->enregistrement_id) {
            return true;
        }

        $r = config('live.replay');
        $chemin = 'lives/live-' . $live->id . '-' . Str::lower(Str::random(10)) . '.mp4';

        $s3 = array_filter([
            'access_key' => $r['cle'],
            'secret' => $r['secret'],
            'bucket' => $r['bucket'],
            'region' => $r['region'] ?: 'auto',
            'endpoint' => $r['endpoint'],
            'force_path_style' => filled($r['endpoint']) ? true : null,
        ], fn ($v) => $v !== null && $v !== '');

        $info = self::appeler('StartRoomCompositeEgress', [
            'room_name' => LiveKit::salle($live),
            'layout' => 'single-speaker',
            'preset' => 'PORTRAIT_H264_720P_30',
            'file_outputs' => [['file_type' => 'MP4', 'filepath' => $chemin, 's3' => $s3]],
        ]);

        $id = $info['egress_id'] ?? $info['egressId'] ?? null;
        if (! $id) {
            return false;
        }

        $live->forceFill(['enregistrement_id' => $id, 'replay_chemin' => $chemin])->saveQuietly();

        return true;
    }

    /** Arrête l'enregistrement : LiveKit termine le fichier et l'envoie. */
    public static function arreter(LiveSession $live): void
    {
        if (! $live->enregistrement_id || ! LiveKit::configure()) {
            return;
        }

        self::appeler('StopEgress', ['egress_id' => $live->enregistrement_id]);
    }
}

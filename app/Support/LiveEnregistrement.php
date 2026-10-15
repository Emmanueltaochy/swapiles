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

    /**
     * Le replay disparaît tout de suite du site ; le fichier est effacé du
     * stockage maintenant, puis encore par live:nettoyer-replays pendant
     * 30 min (LiveKit peut finir d'envoyer la vidéo après la fin du live).
     */
    public static function supprimerReplay(LiveSession $live): void
    {
        $live->forceFill(['replay_supprime_at' => now(), 'replay_publie_at' => null])->saveQuietly();
        self::effacerFichier($live->replay_chemin);
    }

    /** Efface la vidéo des replays supprimés. Renvoie le nombre de fichiers terminés. */
    public static function nettoyer(): int
    {
        $termines = 0;

        LiveSession::query()->whereNotNull('replay_supprime_at')->whereNull('replay_fichier_efface_at')
            ->whereNotNull('replay_chemin')->get()
            ->each(function (LiveSession $live) use (&$termines) {
                if (self::effacerFichier($live->replay_chemin) && $live->replay_supprime_at->lte(now()->subMinutes(30))) {
                    $live->forceFill(['replay_fichier_efface_at' => now()])->saveQuietly();
                    $termines++;
                }
            });

        return $termines;
    }

    /** Suppression d'un fichier dans le stockage S3 (requête signée AWS v4). */
    public static function effacerFichier(?string $chemin): bool
    {
        $r = config('live.replay');
        if (! $chemin || blank($r['cle'] ?? null) || blank($r['secret'] ?? null) || blank($r['bucket'] ?? null)) {
            return false;
        }

        $region = $r['region'] ?: 'auto';
        $cheminEncode = implode('/', array_map('rawurlencode', explode('/', $chemin)));

        if (filled($r['endpoint'])) {
            $base = rtrim($r['endpoint'], '/');
            $hote = parse_url($base, PHP_URL_HOST);
            $uri = '/' . rawurlencode($r['bucket']) . '/' . $cheminEncode;
        } else {
            $hote = $r['bucket'] . '.s3.' . $region . '.amazonaws.com';
            $base = 'https://' . $hote;
            $uri = '/' . $cheminEncode;
        }

        $date = gmdate('Ymd\THis\Z');
        $entetes = [
            'host' => $hote,
            'x-amz-content-sha256' => hash('sha256', ''),
            'x-amz-date' => $date,
        ];
        $entetes['Authorization'] = self::signatureS3('DELETE', $uri, $entetes, $r['cle'], $r['secret'], $region, $date);
        unset($entetes['host']);

        try {
            $reponse = Http::withHeaders($entetes)->timeout(15)->delete(rtrim($base, '/') . $uri);
        } catch (\Throwable $e) {
            Log::warning('Live : suppression du replay impossible', ['erreur' => $e->getMessage()]);

            return false;
        }

        // 204 (supprimé) ou 404 (déjà absent) : le fichier n'est plus là.
        return $reponse->successful() || $reponse->status() === 404;
    }

    /**
     * En-tête Authorization AWS Signature v4 (S3, Cloudflare R2…).
     *
     * @param  array<string,string>  $entetes  en-têtes signés, noms en minuscules
     */
    public static function signatureS3(string $methode, string $uri, array $entetes, string $cle, string $secret, string $region, string $date): string
    {
        ksort($entetes);
        $canoniques = '';
        foreach ($entetes as $nom => $valeur) {
            $canoniques .= $nom . ':' . trim($valeur) . "\n";
        }
        $signes = implode(';', array_keys($entetes));
        $jour = substr($date, 0, 8);
        $portee = $jour . '/' . $region . '/s3/aws4_request';

        $requete = implode("\n", [$methode, $uri, '', $canoniques, $signes, $entetes['x-amz-content-sha256']]);
        $aSigner = implode("\n", ['AWS4-HMAC-SHA256', $date, $portee, hash('sha256', $requete)]);

        $k = hash_hmac('sha256', $jour, 'AWS4' . $secret, true);
        $k = hash_hmac('sha256', $region, $k, true);
        $k = hash_hmac('sha256', 's3', $k, true);
        $k = hash_hmac('sha256', 'aws4_request', $k, true);

        return 'AWS4-HMAC-SHA256 Credential=' . $cle . '/' . $portee . ', SignedHeaders=' . $signes . ', Signature=' . hash_hmac('sha256', $aSigner, $k);
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

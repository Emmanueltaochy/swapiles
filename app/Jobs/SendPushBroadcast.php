<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Support\ApnsService;
use App\Support\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Envoi d'une notification push à tous les appareils enregistrés (ou à un
 * utilisateur précis). Les jetons morts sont supprimés au passage.
 *
 * Deux chemins, parce que les jetons ne sont pas de même nature :
 *   • iOS     -> Apple en direct (APNs). L'app iOS n'embarque pas Firebase,
 *               son jeton est un jeton APNs que Firebase refuse.
 *   • Android -> Firebase (FCM), comme avant.
 */
class SendPushBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Une diffusion ne doit JAMAIS repartir de zéro : si elle dure plus que le
     * délai de relance de la file, une relance renverrait la notification à
     * ceux qui l'ont déjà reçue. Une seule tentative, et la progression est
     * notée pour reprendre là où on en était.
     */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public ?int $userId = null,
        // Ligne du journal des notifications (admin), mise à jour après envoi.
        public ?int $envoiId = null,
    ) {}

    public function handle(FcmService $fcm, ApnsService $apns): void
    {
        if (! FcmService::configured() && ! ApnsService::configured()) {
            return;
        }

        // Notification à un membre : la pastille de l'icône affiche son
        // nombre de notifications non lues (celle-ci comprise).
        $pastille = $this->userId
            ? max(1, \App\Models\Notification::where('user_id', $this->userId)->whereNull('read_at')->count())
            : null;

        // Diffusion à tout le monde : découpée en petits paquets (voir SendPushLot).
        if (! $this->userId) {
            $ids = DeviceToken::query()
                ->whereNotIn('id', DeviceToken::doublonsIds())
                ->orderBy('id')
                ->pluck('id');
            if ($this->envoiId) {
                \App\Models\PushEnvoi::whereKey($this->envoiId)->update([
                    'statut' => $ids->isEmpty() ? 'sans_appareil' : 'envoye',
                    'envoye_at' => now(),
                    'appareils' => $ids->count(),
                    'appareils_ok' => 0,
                ]);
            }
            SendPushLot::planifier($ids, $this->title, $this->body, $this->url, $this->envoiId);

            return;
        }

        $appareils = 0;
        $ok = 0;
        // Anciens jetons d'un même téléphone (réinstallation) : un seul envoi par appareil réel.
        $doublons = DeviceToken::doublonsIds($this->userId);
        $cleProgression = $this->envoiId ? 'push:diffusion:' . $this->envoiId . ':dernier' : null;
        $depuis = $cleProgression ? (int) \Illuminate\Support\Facades\Cache::get($cleProgression, 0) : 0;

        DeviceToken::query()
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->when($depuis > 0, fn ($q) => $q->where('id', '>', $depuis))
            ->when($doublons !== [], fn ($q) => $q->whereNotIn('id', $doublons))
            ->chunkById(200, function ($tokens) use ($fcm, $apns, $pastille, &$appareils, &$ok, $cleProgression) {
                foreach ($tokens as $device) {
                    if ($cleProgression) {
                        \Illuminate\Support\Facades\Cache::put($cleProgression, $device->id, now()->addDay());
                    }
                    $result = self::envoyerVers($device, $this->title, $this->body, $this->url, $fcm, $apns, $pastille);
                    $appareils++;
                    $ok += $result['status'] === 'ok' ? 1 : 0;

                    if ($result['status'] === 'invalid') {
                        $device->delete();
                    }
                }
            });

        \App\Support\PushSuivi::resultat($this->envoiId, $appareils, $ok);
    }

    /**
     * Envoie vers un appareil et CONSERVE le résultat sur la ligne du jeton.
     *
     * Le message d'erreur exact d'Apple ou de Google est enregistré : c'est ce
     * qui permet de diagnostiquer un envoi raté depuis l'administration, sans
     * accès aux journaux du serveur.
     *
     * @return array{status:string,error:?string}
     */
    public static function envoyerVers(
        DeviceToken $device,
        string $title,
        string $body,
        ?string $url,
        FcmService $fcm,
        ApnsService $apns,
        ?int $pastille = null,
    ): array {
        if (self::estIos($device)) {
            $status = $apns->sendToToken($device->token, $title, $body, $url, $pastille);
            $error = $apns->lastError;
        } else {
            $status = $fcm->sendToToken($device->token, $title, $body, $url);
            $error = $fcm->lastError;
        }

        $device->forceFill([
            'last_result' => $status,
            'last_error' => $error,
            'last_sent_at' => now(),
        ])->saveQuietly();

        return ['status' => $status, 'error' => $error];
    }

    /**
     * Jeton à servir par Apple ?
     *
     * On se fie d'abord à la plateforme déclarée par l'app. Pour les jetons
     * enregistrés avant que l'app ne la renseigne, on reconnaît la forme d'un
     * jeton APNs : exactement 64 caractères hexadécimaux. Un jeton Firebase est
     * bien plus long et contient un deux-points.
     */
    public static function estIos(DeviceToken $device): bool
    {
        if ($device->platform === 'ios') {
            return true;
        }

        if ($device->platform !== null) {
            return false;
        }

        return (bool) preg_match('/^[0-9a-f]{64}$/i', (string) $device->token);
    }
}

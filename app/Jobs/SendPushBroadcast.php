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

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public ?int $userId = null,
    ) {}

    public function handle(FcmService $fcm, ApnsService $apns): void
    {
        if (! FcmService::configured() && ! ApnsService::configured()) {
            return;
        }

        DeviceToken::query()
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->chunkById(200, function ($tokens) use ($fcm, $apns) {
                foreach ($tokens as $device) {
                    $result = $this->envoyer($device, $fcm, $apns);

                    if ($result === 'invalid') {
                        $device->delete();
                    }
                }
            });
    }

    /** Aiguille vers Apple ou Firebase selon la plateforme du jeton. */
    private function envoyer(DeviceToken $device, FcmService $fcm, ApnsService $apns): string
    {
        $service = self::estIos($device) ? $apns : $fcm;

        return $service->sendToToken($device->token, $this->title, $this->body, $this->url);
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

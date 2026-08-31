<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Support\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Envoi d'une notification push à tous les appareils enregistrés (ou à un
 * utilisateur précis). Les jetons morts sont supprimés au passage.
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

    public function handle(FcmService $fcm): void
    {
        if (! FcmService::configured()) {
            return;
        }

        DeviceToken::query()
            ->when($this->userId, fn ($q) => $q->where('user_id', $this->userId))
            ->chunkById(200, function ($tokens) use ($fcm) {
                foreach ($tokens as $device) {
                    $result = $fcm->sendToToken($device->token, $this->title, $this->body, $this->url);

                    if ($result === 'invalid') {
                        $device->delete();
                    }
                }
            });
    }
}

<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\PushEnvoi;
use App\Support\ApnsService;
use App\Support\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Un petit paquet d'une diffusion (50 téléphones au plus) : assez court pour
 * ne jamais dépasser le temps accordé à une tâche. Une seule tentative : un
 * paquet n'est JAMAIS renvoyé, pour qu'aucun téléphone ne sonne deux fois.
 */
class SendPushLot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TAILLE = 50;

    public int $tries = 1;

    public int $timeout = 55;

    /** @param int[] $ids */
    public function __construct(
        public array $ids,
        public string $title,
        public string $body,
        public ?string $url,
        public ?int $envoiId = null,
    ) {}

    public function handle(FcmService $fcm, ApnsService $apns): void
    {
        $ok = 0;
        foreach (DeviceToken::whereIn('id', $this->ids)->get() as $device) {
            $result = SendPushBroadcast::envoyerVers($device, $this->title, $this->body, $this->url, $fcm, $apns);
            $ok += $result['status'] === 'ok' ? 1 : 0;
            if ($result['status'] === 'invalid') {
                $device->delete();
            }
        }

        if ($this->envoiId && $ok > 0) {
            PushEnvoi::whereKey($this->envoiId)->increment('appareils_ok', $ok);
        }
    }

    /**
     * Découpe une liste de téléphones en paquets et les met en file.
     *
     * @param  iterable<int>  $ids
     */
    public static function planifier(iterable $ids, string $title, string $body, ?string $url, ?int $envoiId): int
    {
        $n = 0;
        foreach (collect($ids)->chunk(self::TAILLE) as $lot) {
            self::dispatch($lot->values()->all(), $title, $body, $url, $envoiId);
            $n += $lot->count();
        }

        return $n;
    }
}

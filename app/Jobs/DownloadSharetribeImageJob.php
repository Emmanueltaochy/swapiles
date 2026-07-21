<?php

namespace App\Jobs;

use App\Models\ListingImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Télécharge une image Sharetribe depuis son CDN vers le stockage local Laravel.
 *
 * Dispatché par la commande sharetribe:import après création de chaque ListingImage.
 * Met à jour l'URL de l'image avec le chemin local une fois le téléchargement réussi.
 *
 * En cas d'échec, l'URL CDN Sharetribe reste valide tant que Sharetribe est actif,
 * donc le site continue de fonctionner. Le job retentera 3 fois automatiquement.
 */
class DownloadSharetribeImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Nombre de tentatives avant abandon */
    public int $tries = 3;

    /** Délai entre tentatives (secondes) */
    public int $backoff = 60;

    /** Timeout par téléchargement */
    public int $timeout = 30;

    public function __construct(
        public int $listingImageId,
        public string $sharetribeImageUuid,
    ) {}

    public function handle(): void
    {
        $image = ListingImage::find($this->listingImageId);

        if (! $image) {
            Log::warning("DownloadSharetribeImageJob: image #{$this->listingImageId} introuvable");
            return;
        }

        // Si déjà téléchargée localement, on skip
        if (str_starts_with($image->url, '/storage/') || str_starts_with($image->url, 'http://localhost')) {
            return;
        }

        // URL CDN Sharetribe — on essaie plusieurs variantes
        // Sharetribe sert ses images via imgix avec différents formats
        $candidates = [
            "https://sharetribe.imgix.net/{$this->sharetribeImageUuid}",
            "https://sharetribe-prod.imgix.net/{$this->sharetribeImageUuid}",
        ];

        $imageContent = null;
        $contentType = 'image/jpeg';

        foreach ($candidates as $url) {
            try {
                $response = Http::timeout($this->timeout)->get($url);
                if ($response->successful() && str_starts_with($response->header('Content-Type', ''), 'image/')) {
                    $imageContent = $response->body();
                    $contentType = $response->header('Content-Type');
                    break;
                }
            } catch (\Throwable $e) {
                Log::debug("Tentative échouée pour {$url}: ".$e->getMessage());
                continue;
            }
        }

        if (! $imageContent) {
            Log::warning("Impossible de télécharger l'image Sharetribe {$this->sharetribeImageUuid}");
            $this->fail("Image inaccessible sur tous les CDN Sharetribe");
            return;
        }

        // Déterminer l'extension
        $extension = match ($contentType) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => 'jpg',
        };

        // Chemin de stockage : storage/app/public/listings/{listing_id}/{uuid}.{ext}
        $path = "listings/{$image->listing_id}/".Str::random(16).".{$extension}";

        Storage::disk('public')->put($path, $imageContent);

        // Mise à jour de l'URL en base
        $image->update([
            'url' => Storage::disk('public')->url($path),
        ]);

        Log::info("Image Sharetribe {$this->sharetribeImageUuid} téléchargée → {$path}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("DownloadSharetribeImageJob a échoué définitivement", [
            'listing_image_id' => $this->listingImageId,
            'sharetribe_uuid'  => $this->sharetribeImageUuid,
            'error'            => $exception->getMessage(),
        ]);
    }
}

<?php

use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$jsonPath = storage_path('app/sharetribe-export/export.json');
$imagesDir = storage_path('app/sharetribe-export/images');
$logFile = storage_path('logs/sharetribe_images_import_' . date('Ymd_His') . '.log');

$data = collect(json_decode(file_get_contents($jsonPath), true))
    ->where('type', 'listing')
    ->keyBy('id');

$total = Listing::whereNotNull('sharetribe_id')
    ->whereIn('sharetribe_id', $data->keys())
    ->count();

echo "Annonces existantes à traiter : {$total}\n";

$done = 0;
$imagesImported = 0;
$errors = 0;

Listing::whereNotNull('sharetribe_id')
    ->whereIn('sharetribe_id', $data->keys())
    ->orderBy('id')
    ->chunkById(50, function ($listings) use ($data, $imagesDir, $logFile, &$done, &$imagesImported, &$errors, $total) {
        foreach ($listings as $listing) {
            $done++;
            $sharetribeListing = $data[$listing->sharetribe_id] ?? null;
            $imageIds = $sharetribeListing['attributes']['images'] ?? [];

            echo "[{$done}/{$total}] Listing #{$listing->id} - " . count($imageIds) . " images\n";

            if (empty($imageIds)) {
                continue;
            }

            try {
                ListingImage::where('listing_id', $listing->id)->delete();

                $publicDir = storage_path('app/public/listings/' . $listing->id);
                File::ensureDirectoryExists($publicDir);

                foreach ($imageIds as $order => $imageId) {
                    $source = $imagesDir . '/' . $imageId;

                    if (!is_file($source)) {
                        $errors++;
                        file_put_contents($logFile, "MANQUANT listing {$listing->id} image {$imageId}\n", FILE_APPEND);
                        continue;
                    }

                    $mime = mime_content_type($source);
                    $ext = match ($mime) {
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => 'jpg',
                    };

                    $relativePath = 'listings/' . $listing->id . '/' . $imageId . '.' . $ext;
                    $destination = storage_path('app/public/' . $relativePath);

                    copy($source, $destination);

                    ListingImage::create([
                        'listing_id' => $listing->id,
                        'url' => Storage::url($relativePath),
                        'order' => $order,
                    ]);

                    $imagesImported++;
                }
            } catch (Throwable $e) {
                $errors++;
                file_put_contents($logFile, "ERROR listing {$listing->id}: {$e->getMessage()}\n", FILE_APPEND);
                echo "ERROR listing #{$listing->id}\n";
            }
        }
    });

echo "Terminé.\n";
echo "Images importées : {$imagesImported}\n";
echo "Erreurs : {$errors}\n";
echo "Log : {$logFile}\n";

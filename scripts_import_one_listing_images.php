<?php

use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$listingId = 1;

$jsonPath = storage_path('app/sharetribe-export/export.json');
$imagesDir = storage_path('app/sharetribe-export/images');

$data = json_decode(file_get_contents($jsonPath), true);

$listing = Listing::findOrFail($listingId);

$sharetribeListing = collect($data)->first(function ($item) use ($listing) {
    return ($item['type'] ?? null) === 'listing'
        && ($item['id'] ?? null) === $listing->sharetribe_id;
});

if (!$sharetribeListing) {
    exit("Annonce Sharetribe introuvable\n");
}

$imageIds = $sharetribeListing['attributes']['images'] ?? [];

echo "Listing Laravel #{$listing->id} - {$listing->title}\n";
echo "Images à importer : " . count($imageIds) . "\n";

ListingImage::where('listing_id', $listing->id)->delete();

$publicDir = storage_path('app/public/listings/' . $listing->id);
File::ensureDirectoryExists($publicDir);

foreach ($imageIds as $order => $imageId) {
    $source = $imagesDir . '/' . $imageId;

    if (!is_file($source)) {
        echo "MANQUANT {$imageId}\n";
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

    echo "OK {$imageId} => " . Storage::url($relativePath) . " | {$mime}\n";
}

echo "Terminé\n";

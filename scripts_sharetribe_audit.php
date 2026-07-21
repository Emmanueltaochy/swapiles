<?php

use App\Models\Listing;
use App\Models\User;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$jsonPath = storage_path('app/sharetribe-export/export.json');
$imagesDir = storage_path('app/sharetribe-export/images');

$data = json_decode(file_get_contents($jsonPath), true);

if (!is_array($data)) {
    exit("JSON invalide\n");
}

$users = [];
$listings = [];
$transactions = [];
$reviews = [];
$assets = [];

foreach ($data as $item) {
    $type = $item['type'] ?? null;

    if ($type === 'user') {
        $users[] = $item;
    } elseif ($type === 'listing') {
        $listings[] = $item;
    } elseif ($type === 'transaction') {
        $transactions[] = $item;
    } elseif ($type === 'review') {
        $reviews[] = $item;
    } elseif (in_array($type, ['image', 'asset', 'stockImage'], true)) {
        $assets[] = $item;
    }
}

$laravelListingsBySharetribe = Listing::whereNotNull('sharetribe_id')
    ->pluck('id', 'sharetribe_id')
    ->toArray();

$laravelUsersBySharetribe = User::whereNotNull('sharetribe_id')
    ->pluck('id', 'sharetribe_id')
    ->toArray();

$existingListingMatches = 0;
$newListings = 0;
$listingsWithImages = 0;
$totalImageRefs = 0;
$foundImageFiles = 0;
$missingImageFiles = 0;
$sampleMatches = [];
$sampleMissing = [];

foreach ($listings as $listing) {
    $sid = $listing['id'] ?? null;

    if ($sid && isset($laravelListingsBySharetribe[$sid])) {
        $existingListingMatches++;
    } else {
        $newListings++;
    }

    $images = $listing['attributes']['images'] ?? [];

    if (!empty($images)) {
        $listingsWithImages++;
    }

    foreach ($images as $img) {
        $imageId = is_array($img) ? ($img['id'] ?? null) : $img;
        if (!$imageId) continue;

        $totalImageRefs++;

        $file = $imagesDir . '/' . $imageId;

        if (is_file($file)) {
            $foundImageFiles++;

            if (count($sampleMatches) < 5) {
                $sampleMatches[] = [
                    'listing_sharetribe_id' => $sid,
                    'laravel_listing_id' => $laravelListingsBySharetribe[$sid] ?? null,
                    'image_id' => $imageId,
                    'file' => $file,
                    'mime' => trim(shell_exec('file -b ' . escapeshellarg($file))),
                ];
            }
        } else {
            $missingImageFiles++;

            if (count($sampleMissing) < 10) {
                $sampleMissing[] = [
                    'listing_sharetribe_id' => $sid,
                    'laravel_listing_id' => $laravelListingsBySharetribe[$sid] ?? null,
                    'image_id' => $imageId,
                ];
            }
        }
    }
}

$newUsers = 0;
$existingUsers = 0;

foreach ($users as $user) {
    $sid = $user['id'] ?? null;

    if ($sid && isset($laravelUsersBySharetribe[$sid])) {
        $existingUsers++;
    } else {
        $newUsers++;
    }
}

echo "===== AUDIT SHARETRIBE EXPORT =====\n\n";

echo "JSON items total : " . count($data) . "\n";
echo "Users JSON : " . count($users) . "\n";
echo "Listings JSON : " . count($listings) . "\n";
echo "Transactions JSON : " . count($transactions) . "\n";
echo "Reviews JSON : " . count($reviews) . "\n";
echo "Assets/Image items JSON : " . count($assets) . "\n\n";

echo "Laravel listings avec sharetribe_id : " . count($laravelListingsBySharetribe) . "\n";
echo "Annonces JSON déjà présentes Laravel : {$existingListingMatches}\n";
echo "Nouvelles annonces JSON absentes Laravel : {$newListings}\n\n";

echo "Laravel users avec sharetribe_id : " . count($laravelUsersBySharetribe) . "\n";
echo "Users JSON déjà présents Laravel : {$existingUsers}\n";
echo "Nouveaux users JSON absents Laravel : {$newUsers}\n\n";

echo "Listings JSON avec images : {$listingsWithImages}\n";
echo "Références images dans listings JSON : {$totalImageRefs}\n";
echo "Fichiers images trouvés : {$foundImageFiles}\n";
echo "Fichiers images manquants : {$missingImageFiles}\n\n";

echo "Exemples images trouvées :\n";
print_r($sampleMatches);

echo "\nExemples images manquantes :\n";
print_r($sampleMissing);

echo "\n===== FIN AUDIT - AUCUNE MODIFICATION EFFECTUÉE =====\n";

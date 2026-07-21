<?php

use App\Models\User;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$jsonPath = storage_path('app/sharetribe-export/export.json');
$imagesDir = storage_path('app/sharetribe-export/images');

$data = collect(json_decode(file_get_contents($jsonPath), true));

$usersJson = $data->where('type', 'user')->keyBy('id');
$listingsJson = $data->where('type', 'listing')->keyBy('id');

function slugValue($value) {
    return $value ? Str::slug($value) : null;
}

function territoryLabel($value) {
    return match ($value) {
        'la-reunion' => 'La Réunion',
        'martinique' => 'Martinique',
        'guadeloupe' => 'Guadeloupe',
        'guyane' => 'Guyane',
        'mayotte' => 'Mayotte',
        default => 'La Réunion',
    };
}

$newUsers = 0;
$newListings = 0;
$newImages = 0;

DB::beginTransaction();

try {
    foreach ($usersJson as $sid => $item) {
        if (User::where('sharetribe_id', $sid)->exists()) {
            continue;
        }

        $attr = $item['attributes'] ?? [];
        $profile = $attr['profile'] ?? [];

        $name = $profile['displayName']
            ?? trim(($profile['firstName'] ?? '') . ' ' . ($profile['lastName'] ?? ''));

        if (!$name) {
            $name = 'Utilisateur Swap’Îles';
        }

        User::create([
            'sharetribe_id' => $sid,
            'name' => $name,
            'email' => $attr['email'] ?? ('user-' . $sid . '@import.local'),
            'password' => Hash::make(Str::random(32)),
            'avatar' => null,
            'stripe_account_id' => $attr['stripeAccountId'] ?? null,
            'territoire' => territoryLabel($profile['publicData']['territoire_de_residence'] ?? 'la-reunion'),
            'is_pro' => false,
            'is_banned' => (bool) ($attr['banned'] ?? false),
            'rating' => 0,
            'transactions_count' => 0,
        ]);

        $newUsers++;
    }

    foreach ($listingsJson as $sid => $item) {
        if (Listing::where('sharetribe_id', $sid)->exists()) {
            continue;
        }

        $attr = $item['attributes'] ?? [];
        $public = $attr['publicData'] ?? [];
        $authorSid = $attr['author'] ?? null;

        $user = $authorSid ? User::where('sharetribe_id', $authorSid)->first() : null;

        if (!$user) {
            continue;
        }

        $state = $attr['state'] ?? 'published';

        $listing = new Listing();
        $listing->forceFill([
            'sharetribe_id' => $sid,
            'user_id' => $user->id,
            'title' => $attr['title'] ?? 'Annonce sans titre',
            'description' => $attr['description'] ?? null,
            'price' => (float) ($attr['price']['amount'] ?? 0),
            'currency' => $attr['price']['currency'] ?? 'EUR',
            'listing_type' => $public['listingType'] ?? 'achat',
            'status' => in_array($state, ['published', 'closed', 'draft'], true) ? $state : 'published',
            'territoire' => territoryLabel($public['Territoire'] ?? 'la-reunion'),
            'category_level1' => slugValue($public['categoryLevel1'] ?? null),
            'category_level2' => slugValue($public['categoryLevel2'] ?? null),
            'category_level3' => slugValue($public['categoryLevel3'] ?? null),
            'etat' => $public['etat'] ?? null,
            'marque' => $public['marque'] ?? null,
            'taille' => $public['taille'] ?? null,
            'couleurs' => $public['Couleurs'] ?? [],
            'location_address' => $public['location']['address'] ?? null,
            'pickup_enabled' => (bool) ($public['pickupEnabled'] ?? true),
            'shipping_enabled' => (bool) ($public['shippingEnabled'] ?? false),
            'allows_hand_delivery' => (bool) ($public['pickupEnabled'] ?? true),
            'allows_colissimo' => false,
            'requires_online_payment' => false,
            'shipping_price' => isset($public['shippingPriceInSubunitsOneItem']) ? ((float) $public['shippingPriceInSubunitsOneItem'] / 100) : 0,
            'weight_kg' => null,
            'views_count' => 0,
            'created_at' => $attr['createdAt'] ?? now(),
            'updated_at' => now(),
        ]);
        $listing->save();

        foreach (($attr['images'] ?? []) as $order => $imageId) {
            $source = $imagesDir . '/' . $imageId;

            if (!is_file($source)) {
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

            File::ensureDirectoryExists(dirname($destination));
            copy($source, $destination);

            ListingImage::create([
                'listing_id' => $listing->id,
                'url' => Storage::url($relativePath),
                'order' => $order,
            ]);

            $newImages++;
        }

        $newListings++;
    }

    DB::commit();

    echo "Import terminé.\n";
    echo "Nouveaux utilisateurs : {$newUsers}\n";
    echo "Nouvelles annonces : {$newListings}\n";
    echo "Nouvelles images : {$newImages}\n";
} catch (Throwable $e) {
    DB::rollBack();
    echo "ERREUR : " . $e->getMessage() . "\n";
    exit(1);
}

<?php

use App\Models\ListingImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$images = ListingImage::where('url', 'like', 'https://sharetribe.imgix.net/%')
    ->orderBy('id')
    ->take(10)
    ->get();

foreach ($images as $image) {
    echo "Image {$image->id} / listing {$image->listing_id}\n";

    try {
        $response = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0',
                'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            ])
            ->get($image->url);

        if (!$response->successful()) {
            echo "FAILED HTTP {$response->status()}\n";
            continue;
        }

        $contentType = $response->header('Content-Type') ?? '';
        $ext = str_contains($contentType, 'png') ? 'png' : 'jpg';

        $filename = Str::random(40) . '.' . $ext;
        $path = 'listings/' . $image->listing_id . '/' . $filename;

        Storage::disk('public')->put($path, $response->body());

        $localUrl = Storage::url($path);

        echo "OK => {$localUrl}\n";

    } catch (Throwable $e) {
        echo "ERROR {$e->getMessage()}\n";
    }
}

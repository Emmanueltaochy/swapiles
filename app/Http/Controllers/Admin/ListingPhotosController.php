<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ListingPhotosController extends Controller
{
    public function update(Request $request, Listing $listing)
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);

        $request->validate([
            'images.*' => ['nullable', 'image', 'max:5120'],
            'delete' => ['nullable', 'array'],
            'delete.*' => ['integer'],
        ]);

        // Suppression des photos cochées
        $deleteIds = array_filter((array) $request->input('delete', []));
        if (! empty($deleteIds)) {
            $images = ListingImage::where('listing_id', $listing->id)
                ->whereIn('id', $deleteIds)
                ->get();

            foreach ($images as $img) {
                try {
                    // On retire le préfixe /storage/ pour retrouver le chemin sur le disque public.
                    $path = ltrim(preg_replace('#^/?storage/#', '', (string) $img->url), '/');
                    if ($path && Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }

                $img->delete();
            }
        }

        // Ajout des nouvelles photos (même convention que le dépôt côté vendeur)
        if ($request->hasFile('images')) {
            $currentCount = $listing->images()->count();

            foreach ($request->file('images') as $index => $file) {
                if (! $file) {
                    continue;
                }

                $path = $file->store('listings/' . $listing->id, 'public');

                ListingImage::create([
                    'listing_id' => $listing->id,
                    'url' => Storage::url($path),
                    'order' => $currentCount + $index,
                ]);
            }
        }

        return back()->with('status', 'Photos de l’annonce mises à jour.');
    }
}

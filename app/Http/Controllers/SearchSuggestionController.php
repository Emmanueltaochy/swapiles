<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\Request;

class SearchSuggestionController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        return Listing::query()
            ->with('images')
            ->where('status', 'published')
            ->where(function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%")
                    ->orWhere('marque', 'like', "%{$q}%")
                    ->orWhere('category_level1', 'like', "%{$q}%")
                    ->orWhere('category_level2', 'like', "%{$q}%")
                    ->orWhere('category_level3', 'like', "%{$q}%");
            })
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn ($listing) => [
                'title' => $listing->title,
                'price' => $listing->price,
                'url' => route('listings.show', $listing),
                'image' => $listing->images->first()?->url,
            ]);
    }
}

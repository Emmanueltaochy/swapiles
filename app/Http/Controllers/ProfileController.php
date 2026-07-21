<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Review;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request, User $user)
    {
        $listings = $user->listings()
            ->with('images')
            ->withCount('favoritedBy')
            ->where('status', 'published')
            ->latest()
            ->paginate(24);

        $reviews = $user->reviewsReceived()
            ->latest()
            ->take(20)
            ->get();

        $reviewsCount = $user->reviewsReceived()->count();

        $soldListingsCount = $user->listings()
            ->where('status', 'sold')
            ->count();

        $publishedListingsCount = $user->listings()
            ->where('status', 'published')
            ->count();

        $totalViewsCount = (int) $user->listings()
            ->where('status', 'published')
            ->sum('views_count');

        $totalFavoritesCount = (int) $user->listings()
            ->where('status', 'published')
            ->withCount('favoritedBy')
            ->get()
            ->sum('favorited_by_count');

        $activeTab = $request->get('tab', 'annonces');

        $firstListing = $user->listings()
            ->where('status', 'published')
            ->latest()
            ->first();

        return view('profiles.show', compact(
            'user',
            'listings',
            'reviews',
            'reviewsCount',
            'activeTab',
            'firstListing',
            'soldListingsCount',
            'publishedListingsCount',
            'totalViewsCount',
            'totalFavoritesCount'
        ));
    }
}

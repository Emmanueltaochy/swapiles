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

        // Rang au classement des dressings : n'affiche le badge que si le
        // vendeur est dans le Top (jouer sur l'ego / la preuve sociale).
        $dressingRank = \App\Support\DressingLeaderboard::rankOf($user->id);
        if ($dressingRank && $dressingRank > (int) config('leaderboard.top', 10)) {
            $dressingRank = null;
        }

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
            'totalFavoritesCount',
            'dressingRank'
        ));
    }
}

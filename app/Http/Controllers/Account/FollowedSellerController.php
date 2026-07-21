<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;

class FollowedSellerController extends Controller
{
    public function index()
    {
        $sellers = auth()->user()
            ->followedSellers()
            ->withCount(['listings as published_listings_count' => function ($q) {
                $q->where('status', 'published');
            }])
            ->latest('seller_follows.created_at')
            ->paginate(24);

        return view('account.followed-sellers.index', compact('sellers'));
    }
}

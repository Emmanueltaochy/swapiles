<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;

class FollowerController extends Controller
{
    public function index()
    {
        $followers = auth()->user()
            ->followers()
            ->latest('seller_follows.created_at')
            ->paginate(24);

        return view('account.followers.index', compact('followers'));
    }
}

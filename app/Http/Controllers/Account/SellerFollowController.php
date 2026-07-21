<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;

class SellerFollowController extends Controller
{
    public function toggle(User $user)
    {
        abort_if(auth()->id() === $user->id, 403);

        $auth = auth()->user();

        $exists = $auth->followedSellers()
            ->where('seller_id', $user->id)
            ->exists();

        if ($exists) {
            $auth->followedSellers()->detach($user->id);
            $following = false;
        } else {
            $auth->followedSellers()->attach($user->id);
            $following = true;
        }

        $count = $user->followers()->count();

        if (request()->expectsJson()) {
            return response()->json([
                'following' => $following,
                'count' => $count,
            ]);
        }

        return back()->with('status', $following ? 'Vendeur suivi.' : 'Vendeur retiré de vos suivis.');
    }
}

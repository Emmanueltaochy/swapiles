<?php

namespace App\Http\Controllers;

use App\Support\DressingLeaderboard;

/**
 * Page publique « Meilleurs dressings » : Top des vendeurs classés par
 * engagement (vues, favoris, messages, ventes, avis).
 */
class LeaderboardController extends Controller
{
    public function index()
    {
        $ranked = DressingLeaderboard::ranked();

        $top = $ranked->take((int) config('leaderboard.top', 10));

        $myRank = null;
        if (auth()->check()) {
            $myRank = optional($ranked->firstWhere('user_id', auth()->id()))->rank;
        }

        return view('leaderboard.index', [
            'top' => $top,
            'myRank' => $myRank,
            'totalRanked' => $ranked->count(),
        ]);
    }
}

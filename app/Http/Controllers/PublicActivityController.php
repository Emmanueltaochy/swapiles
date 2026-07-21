<?php

namespace App\Http\Controllers;

use App\Models\Transaction;

class PublicActivityController extends Controller
{
    public function recent()
    {
        $activities = Transaction::query()
            ->with(['buyer', 'listing'])
            ->whereIn('status', ['paid', 'completed'])
            ->whereHas('buyer')
            ->whereHas('listing')
            ->latest()
            ->limit(12)
            ->get()
            ->map(function ($transaction) {
                return [
                    'name' => explode(' ', trim($transaction->buyer->name))[0] ?: 'Un membre',
                    'product' => $transaction->listing->title,
                ];
            })
            ->values();

        return response()->json($activities);
    }
}

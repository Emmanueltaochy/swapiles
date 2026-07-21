<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Transaction;
use App\Models\Notification;
use App\Models\Favorite;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();

        $listings = Listing::with('images')->withCount('favoritedBy')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(12);

        $sales = Transaction::with(['listing.images', 'buyer'])
            ->where('seller_id', $user->id)
            ->latest()
            ->get();

        $purchases = Transaction::with(['listing.images', 'seller'])
            ->where('buyer_id', $user->id)
            ->latest()
            ->get();

        $netAmount = function ($transaction) {
            if ((float) $transaction->seller_amount > 0) {
                return (float) $transaction->seller_amount;
            }

            return max(0,
                (float) $transaction->amount
                - (float) $transaction->commission
                - (float) $transaction->buyer_protection_fee
                - (float) $transaction->shipping_fee
            );
        };

        $realSales = $sales
            ->whereIn('status', ['paid', 'completed'])
            ->filter(fn ($transaction) => $transaction->listing !== null)
            ->values();

        $pendingCheckouts = $sales->where('status', 'pending');

        $unfinishedOrdersCount = $pendingCheckouts->count();

        $pendingSalesAmount = $realSales
            ->where('status', 'paid')
            ->sum($netAmount);

        $availableAmount = $realSales
            ->where('status', 'completed')
            ->filter(fn ($transaction) => empty($transaction->transferred_at))
            ->sum($netAmount);

        $completedSalesAmount = $realSales
            ->where('status', 'completed')
            ->sum($netAmount);

        $commissionAmount = $realSales->sum('commission');

        $favoritesReceivedCount = Favorite::whereIn(
            'listing_id',
            $listings->pluck('id')
        )->count();

        $totalListingViews = Listing::where('user_id', $user->id)
            ->sum('views_count');

        $notifications = Notification::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('account.dashboard', compact(
            'user',
            'listings',
            'sales',
            'realSales',
            'pendingCheckouts',
            'purchases',
            'unfinishedOrdersCount',
            'pendingSalesAmount',
            'availableAmount',
            'completedSalesAmount',
            'commissionAmount',
              'notifications',
              'favoritesReceivedCount',
              'totalListingViews'
        ));
    }
}

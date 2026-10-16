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

        // Aperçu de 6 annonces ; « Voir toutes » affiche la liste complète,
        // filtrable par état, sans allonger la page de compte.
        $toutesLesAnnonces = request('annonces') === 'toutes';
        $filtreAnnonces = in_array(request('etat'), ['published', 'draft', 'sold'], true) ? request('etat') : null;

        $listingsQuery = Listing::with('images')->withCount(['favorisRecus as favorited_by_count'])
            ->where('user_id', $user->id)
            ->when($filtreAnnonces, fn ($query) => $query->where('status', $filtreAnnonces))
            ->latest();

        $listings = $toutesLesAnnonces
            ? $listingsQuery->paginate(24)->withQueryString()->fragment('mes-annonces')
            : $listingsQuery->take(6)->get();

        // Compteurs sur TOUTES les annonces (et non la page affichée).
        $annoncesParEtat = Listing::where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

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

        // Montants d'argent = UNIQUEMENT les ventes sécurisées (paiement en
        // ligne). Espèces/don/échange ne gonflent pas le solde (bug wallet).
        $securedSales = \App\Support\SellerWallet::securedOnly($realSales);

        $pendingCheckouts = $sales->where('status', 'pending');

        $unfinishedOrdersCount = $pendingCheckouts->count();

        $pendingSalesAmount = $securedSales
            ->where('status', 'paid')
            ->sum($netAmount);

        $availableAmount = $securedSales
            ->where('status', 'completed')
            ->filter(fn ($transaction) => empty($transaction->transferred_at))
            ->sum($netAmount);

        $completedSalesAmount = $securedSales
            ->where('status', 'completed')
            ->sum($netAmount);

        $commissionAmount = $realSales->sum('commission');

        $favoritesReceivedCount = Favorite::whereIn(
            'listing_id',
            Listing::where('user_id', $user->id)->select('id')
        )->where('user_id', '!=', $user->id)->count();

        $totalListingViews = Listing::where('user_id', $user->id)
            ->sum('views_count');

        $notifications = Notification::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();

        // Les notifications restent « non lues » (pastille rouge) jusqu'à ce que
        // le membre ouvre la page Notifications : ouvrir Mon compte ne suffit pas.

        return view('account.dashboard', compact(
            'user',
            'listings',
            'annoncesParEtat',
            'toutesLesAnnonces',
            'filtreAnnonces',
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

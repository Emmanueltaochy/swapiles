<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Notification;
use App\Notifications\ListingFavoritedNotification;
use App\Support\AdminEvent;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Annonces pour lesquelles l'utilisateur a fait une DEMANDE DE LIVRAISON
        // (bouton « Demander la livraison » qui ajoute aussi aux favoris).
        $interestIds = \App\Models\ListingInterest::where('buyer_id', $user->id)
            ->pluck('listing_id');

        // Comptes par onglet.
        $favListingIds = $user->favorites()->pluck('listings.id');
        $countAll = $favListingIds->count();
        $countLivraison = $favListingIds->intersect($interestIds)->count();
        $countDirect = $countAll - $countLivraison;

        $filter = request('filter', 'all');
        if (! in_array($filter, ['all', 'direct', 'livraison'], true)) {
            $filter = 'all';
        }

        $query = $user->favorites()->with('images', 'user');

        if ($filter === 'direct') {
            $query->whereNotIn('listings.id', $interestIds);
        } elseif ($filter === 'livraison') {
            $query->whereIn('listings.id', $interestIds);
        }

        $favorites = $query->latest('favorites.created_at')
            ->paginate(40)
            ->withQueryString();

        return view('account.favorites.index', [
            'favorites' => $favorites,
            'interestIds' => $interestIds,
            'filter' => $filter,
            'countAll' => $countAll,
            'countDirect' => $countDirect,
            'countLivraison' => $countLivraison,
        ]);
    }

    public function toggle(Listing $listing)
    {
        $user = auth()->user();

        if ($user->favorites()->where('listing_id', $listing->id)->exists()) {
            $user->favorites()->detach($listing->id);
            $favorited = false;
        } else {
            $user->favorites()->attach($listing->id);
            $favorited = true;

            if ($listing->user_id && $listing->user_id !== $user->id) {
                Notification::create([
                    'user_id' => $listing->user_id,
                    'type' => 'favorite_added',
                    'title' => 'Nouveau favori ❤️',
                    'message' => $user->name . ' a ajouté "' . $listing->title . '" à ses favoris.',
                    'url' => route('listings.show', $listing, absolute: false),
                ]);

                try {
                    $listing->user?->notify(new ListingFavoritedNotification($listing, $user));
                } catch (\Throwable $e) {
                    report($e);
                }

                AdminEvent::notify(
                    'Annonce ajoutée en favori',
                    ($user->name ?? 'Un membre') . ' a ajouté en favori : ' . $listing->title,
                    route('listings.show', $listing)
                );
            }
        }

        $count = $listing->favoritedBy()->count();

        if (request()->expectsJson()) {
            return response()->json([
                'favorited' => $favorited,
                'count' => $count,
            ]);
        }

        return back();
    }
}

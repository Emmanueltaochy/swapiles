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
        $favorites = Auth::user()
            ->favorites()
            ->with('images', 'user')
            ->latest('favorites.created_at')
            ->paginate(40);

        return view('account.favorites.index', compact('favorites'));
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

<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\ListingOffer;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Notifications\OfferReceivedNotification;
use App\Notifications\OfferStatusNotification;
use App\Jobs\SendOfferEmail;

class ListingOfferController extends Controller
{
    public function store(Request $request, Listing $listing)
    {
        abort_unless(Auth::check(), 403);
        abort_if($listing->user_id === Auth::id(), 403);

        if ($listing->status === 'sold') {
            return redirect()->route('listings.show', $listing)
                ->with('status', "Cet article n'est plus disponible.");
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $offer = ListingOffer::create([
            'listing_id' => $listing->id,
            'buyer_id' => Auth::id(),
            'seller_id' => $listing->user_id,
            'amount' => $data['amount'],
            'message' => $data['message'] ?? null,
        ]);

        Message::create([
            'listing_id' => $listing->id,
            'sender_id' => Auth::id(),
            'receiver_id' => $listing->user_id,
            'body' => "💸 Nouvelle offre : {$offer->amount} €\n\n" . ($offer->message ?: 'Aucun message ajouté.'),
        ]);

        SendOfferEmail::dispatch($offer->id, $listing->user_id, 'received');

        return redirect()
            ->route('account.messages.show', [
                'listing' => $listing,
                'user' => $listing->user,
            ])
            ->with('status', 'Votre offre a bien été envoyée au vendeur.');
    }

    public function accept(ListingOffer $offer)
    {
        abort_unless(Auth::id() === $offer->seller_id, 403);

        $offer->update(['status' => 'accepted']);

        Message::create([
            'listing_id' => $offer->listing_id,
            'sender_id' => Auth::id(),
            'receiver_id' => $offer->buyer_id,
            'body' => "✅ Votre offre de {$offer->amount} € a été acceptée.\n\nVous pouvez maintenant finaliser l’achat au prix accepté.",
        ]);

        SendOfferEmail::dispatch($offer->id, $offer->buyer_id, 'accepted');

        return back()->with('status', 'Offre acceptée.');
    }

    public function refuse(ListingOffer $offer)
    {
        abort_unless(Auth::id() === $offer->seller_id, 403);

        $offer->update(['status' => 'refused']);

        Message::create([
            'listing_id' => $offer->listing_id,
            'sender_id' => Auth::id(),
            'receiver_id' => $offer->buyer_id,
            'body' => "❌ Votre offre de {$offer->amount} € a été refusée.",
        ]);

        SendOfferEmail::dispatch($offer->id, $offer->buyer_id, 'refused');

        return back()->with('status', 'Offre refusée.');
    }
    public function counter(Request $request, Listing $listing, User $user)
    {
        abort_unless(Auth::check(), 403);
        abort_unless($listing->user_id === Auth::id(), 403);
        abort_if($user->id === Auth::id(), 403);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $offer = ListingOffer::create([
            'listing_id' => $listing->id,
            'buyer_id' => $user->id,
            'seller_id' => Auth::id(),
            'amount' => $data['amount'],
            'status' => 'accepted',
            'message' => $data['message'] ?? null,
        ]);

        Message::create([
            'listing_id' => $listing->id,
            'sender_id' => Auth::id(),
            'receiver_id' => $user->id,
            'body' => "✅ Contre-offre vendeur : {$offer->amount} €\n\n" . ($offer->message ?: 'Vous pouvez finaliser l’achat à ce prix.'),
        ]);

        SendOfferEmail::dispatch($offer->id, $user->id, 'accepted');

        return redirect()
            ->route('account.messages.show', [
                'listing' => $listing,
                'user' => $user,
            ])
            ->with('status', 'Contre-offre envoyée.');
    }
}

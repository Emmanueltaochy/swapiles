<?php

namespace App\Http\Controllers;

use App\Models\ListingOffer;
use App\Models\Message;
use Illuminate\Http\Request;

class OfferActionController extends Controller
{
    public function accept(ListingOffer $offer)
    {
        abort_if(auth()->id() !== $offer->seller_id, 403);

        $offer->update([
            'status' => 'accepted',
        ]);

        ListingOffer::where('listing_id', $offer->listing_id)
            ->where('id', '!=', $offer->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'declined',
            ]);

        Message::create([
            'listing_id' => $offer->listing_id,
            'sender_id' => auth()->id(),
            'receiver_id' => $offer->buyer_id,
            'message' => "✅ Offre acceptée : {$offer->amount} €",
            'type' => 'offer_accepted',
        ]);

        return back()->with('success', 'Offre acceptée.');
    }

    public function decline(ListingOffer $offer)
    {
        abort_if(auth()->id() !== $offer->seller_id, 403);

        $offer->update([
            'status' => 'declined',
        ]);

        Message::create([
            'listing_id' => $offer->listing_id,
            'sender_id' => auth()->id(),
            'receiver_id' => $offer->buyer_id,
            'message' => "❌ Offre refusée : {$offer->amount} €",
            'type' => 'offer_declined',
        ]);

        return back()->with('success', 'Offre refusée.');
    }
}

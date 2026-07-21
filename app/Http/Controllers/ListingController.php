<?php

namespace App\Http\Controllers;

use App\Jobs\SendMessageReceivedEmail;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListingController extends Controller
{
    public function show(Listing $listing)
    {
        $listing->increment('views_count');
        $listing->loadCount('favoritedBy');
        abort_if($listing->status !== 'published', 404);

        $listing->load(['images' => fn($q) => $q->orderBy('order')]);

        return view('listings.show', compact('listing'));
    }

    public function requestMode(Request $request, Listing $listing, string $mode)
    {
        abort_unless(Auth::check(), 403);
        abort_if(Auth::id() === $listing->user_id, 403);
        abort_if($listing->status !== 'published', 404);

        abort_unless(in_array($mode, ['cash', 'exchange', 'don'], true), 404);

        $buyer = Auth::user();
        $seller = $listing->user;

        $label = match ($mode) {
            'cash' => 'payer en espèces',
            'exchange' => 'faire un échange',
            'don' => 'récupérer ce don',
        };

        $body = match ($mode) {
            'cash' => "💵 Bonjour, je souhaite acheter cet article en espèces lors d’une remise en main propre.",
            'exchange' => "🔄 Bonjour, je souhaite proposer un échange pour cet article.",
            'don' => "🎁 Bonjour, je souhaite récupérer ce don.",
        };

        $message = Message::create([
            'listing_id' => $listing->id,
            'sender_id' => $buyer->id,
            'receiver_id' => $seller->id,
            'body' => $body,
        ]);

        try {
            Notification::create([
                'user_id' => $seller->id,
                'type' => 'listing_' . $mode . '_request',
                'title' => 'Nouvelle demande 💬',
                'message' => ($buyer->name ?? 'Un membre') . ' souhaite ' . $label . ' : ' . $listing->title,
                'url' => route('account.messages.show', [
                    'listing' => $listing,
                    'user' => $buyer,
                ]),
            ]);

            SendMessageReceivedEmail::dispatch($message->id, $seller->id);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('account.messages.show', [
                'listing' => $listing,
                'user' => $seller,
            ])
            ->with('status', 'Votre demande a été envoyée au vendeur.');
    }
}

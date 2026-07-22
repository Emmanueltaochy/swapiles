<?php

namespace App\Http\Controllers;

use App\Jobs\SendListingViewedEmail;
use App\Jobs\SendMessageReceivedEmail;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ListingController extends Controller
{
    public function show(Request $request, Listing $listing)
    {
        // On autorise la consultation des annonces en ligne ET vendues
        // (sinon un clic sur une notification d'un article vendu → 404).
        abort_unless(in_array($listing->status, ['published', 'sold'], true), 404);

        if ($listing->status === 'published') {
            $listing->increment('views_count');
            $this->notifySellerOfView($request, $listing);
        }

        $listing->loadCount('favoritedBy');
        $listing->load(['images' => fn($q) => $q->orderBy('order')]);

        return view('listings.show', compact('listing'));
    }

    /**
     * E-mail au vendeur « quelqu'un vient de regarder votre annonce ».
     * On ne prévient jamais le vendeur pour ses propres vues et on limite
     * à un e-mail par visiteur et par annonce toutes les 24 h (anti-spam).
     */
    private function notifySellerOfView(Request $request, Listing $listing): void
    {
        try {
            if (Auth::check() && Auth::id() === $listing->user_id) {
                return;
            }

            $viewerKey = Auth::check()
                ? 'u' . Auth::id()
                : 'ip' . sha1($request->ip() . '|' . (string) $request->userAgent());

            $throttleKey = 'listing_view_email:' . $listing->id . ':' . $viewerKey;

            // Cache::add ne renvoie true qu'une seule fois par fenêtre : sert de verrou.
            if (Cache::add($throttleKey, 1, now()->addHours(24))) {
                SendListingViewedEmail::dispatch($listing->id);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function requestMode(Request $request, Listing $listing, string $mode)
    {
        abort_unless(Auth::check(), 403);
        abort_if(Auth::id() === $listing->user_id, 403);

        if ($listing->status !== 'published') {
            return redirect()->route('listings.show', $listing)
                ->with('status', "Cet article n'est plus disponible.");
        }

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

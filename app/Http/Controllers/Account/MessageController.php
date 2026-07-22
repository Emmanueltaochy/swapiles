<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Message;
use App\Models\ListingOffer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendMessageReceivedEmail;

class MessageController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $messages = Message::query()
            ->with(['listing.images', 'sender', 'receiver'])
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)
                  ->orWhere('receiver_id', $userId);
            })
            ->latest()
            ->get();

        $conversations = $messages
            ->groupBy(function ($message) use ($userId) {
                $otherId = $message->sender_id === $userId ? $message->receiver_id : $message->sender_id;
                return $message->listing_id . '-' . $otherId;
            })
            ->map(function ($items) {
                return $items->sortByDesc('created_at')->first();
            })
            ->sortByDesc('created_at');


        \App\Models\Message::where('receiver_id', auth()->id())
            ->whereNull('read_at')
            ->update([
                'read_at' => now()
            ]);

        return view('account.messages.index', compact('conversations'));

    }

    public function show(Listing $listing, User $user)
    {
        $authId = Auth::id();

        abort_if($authId === $user->id, 403);

        $isSeller = $listing->user_id === $authId;
        $isBuyer = $listing->user_id === $user->id;

        abort_unless($isSeller || $isBuyer, 403);

        Message::where('listing_id', $listing->id)
            ->where('sender_id', $user->id)
            ->where('receiver_id', $authId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = Message::query()
            ->with(['sender', 'receiver'])
            ->where('listing_id', $listing->id)
            ->where(function ($q) use ($authId, $user) {
                $q->where(function ($sub) use ($authId, $user) {
                    $sub->where('sender_id', $authId)->where('receiver_id', $user->id);
                })->orWhere(function ($sub) use ($authId, $user) {
                    $sub->where('sender_id', $user->id)->where('receiver_id', $authId);
                });
            })
            ->oldest()
            ->get();

        $pendingOffers = ListingOffer::where('listing_id', $listing->id)
            ->where('seller_id', auth()->id())
            ->where('buyer_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        $exchangeProposals = \App\Models\ExchangeProposal::where('listing_id', $listing->id)
            ->where(function ($q) use ($authId, $user) {
                $q->where('proposer_id', $authId)->where('seller_id', $user->id)
                  ->orWhere('proposer_id', $user->id)->where('seller_id', $authId);
            })
            ->with(['offeredListing.images', 'proposer'])
            ->latest()
            ->get();

        return view('account.messages.show', compact('listing', 'user', 'messages', 'pendingOffers', 'exchangeProposals'));
    }


    public function showGeneral(User $user)
    {
        $authId = Auth::id();

        abort_if($authId === $user->id, 403);

        Message::whereNull('listing_id')
            ->where('sender_id', $user->id)
            ->where('receiver_id', $authId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = Message::query()
            ->with(['sender', 'receiver'])
            ->whereNull('listing_id')
            ->where(function ($q) use ($authId, $user) {
                $q->where(function ($sub) use ($authId, $user) {
                    $sub->where('sender_id', $authId)->where('receiver_id', $user->id);
                })->orWhere(function ($sub) use ($authId, $user) {
                    $sub->where('sender_id', $user->id)->where('receiver_id', $authId);
                });
            })
            ->oldest()
            ->get();

        $listing = null;
        $pendingOffers = collect();
        $exchangeProposals = collect();

        return view('account.messages.show', compact('listing', 'user', 'messages', 'pendingOffers', 'exchangeProposals'));
    }

    public function storeGeneral(Request $request, User $user)
    {
        $authId = Auth::id();

        abort_if($authId === $user->id, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
        ]);

        $message = Message::create([
            'listing_id' => null,
            'sender_id' => $authId,
            'receiver_id' => $user->id,
            'body' => $data['body'],
        ]);

        $this->safeNotifyMessage($user, $message);

        return redirect()->route('account.messages.show.general', [
            'user' => $user,
        ]);
    }


    public function start(Listing $listing)
    {
        abort_if($listing->user_id === Auth::id(), 403);

        return redirect()->route('account.messages.show', [
            'listing' => $listing,
            'user' => $listing->user,
        ]);
    }

    private function safeNotifyMessage(User $user, Message $message): void
    {
        try {
            $message = $message->loadMissing(['listing', 'sender']);

            $url = $message->listing
                ? route('account.messages.show', ['listing' => $message->listing, 'user' => $message->sender])
                : route('account.messages.show.general', ['user' => $message->sender]);

            \App\Models\Notification::create([
                'user_id' => $user->id,
                'type' => 'message_received',
                'title' => 'Nouveau message 💬',
                'message' => ($message->sender->name ?? 'Un membre') . ' vous a envoyé un message.',
                'url' => $url,
            ]);

            SendMessageReceivedEmail::dispatch($message->id, $user->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function store(Request $request, Listing $listing, User $user)
    {
        $authId = Auth::id();

        abort_if($authId === $user->id, 403);

        $isSeller = $listing->user_id === $authId;
        $isBuyer = $listing->user_id === $user->id;

        abort_unless($isSeller || $isBuyer, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
        ]);

        $message = Message::create([
            'listing_id' => $listing->id,
            'sender_id' => $authId,
            'receiver_id' => $user->id,
            'body' => $data['body'],
        ]);

        $this->safeNotifyMessage($user, $message);

        return redirect()->route('account.messages.show', [
            'listing' => $listing,
            'user' => $user,
        ]);
    }
}

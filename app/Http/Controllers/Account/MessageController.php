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

        $request->validate(self::messageRules(), self::messageMessages());

        $message = $this->buildMessage($request, null, $user->id);

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
                ? route('account.messages.show', ['listing' => $message->listing, 'user' => $message->sender], absolute: false)
                : route('account.messages.show.general', ['user' => $message->sender], absolute: false);

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

        $request->validate(self::messageRules(), self::messageMessages());

        $message = $this->buildMessage($request, $listing->id, $user->id);

        $this->safeNotifyMessage($user, $message);

        return redirect()->route('account.messages.show', [
            'listing' => $listing,
            'user' => $user,
        ]);
    }

    /**
     * Règles : un message OU une pièce jointe (photo/vidéo), l'un des deux suffit.
     */
    private static function messageRules(): array
    {
        return [
            'body' => ['nullable', 'required_without:attachment', 'string', 'max:3000'],
            'attachment' => [
                'nullable', 'required_without:body', 'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,video/mp4,video/quicktime,video/webm,video/3gpp',
                'max:51200', // 50 Mo
            ],
        ];
    }

    private static function messageMessages(): array
    {
        return [
            'body.required_without' => 'Écris un message ou ajoute une photo/vidéo.',
            'attachment.required_without' => 'Écris un message ou ajoute une photo/vidéo.',
            'attachment.mimetypes' => 'Format non supporté : ajoute une photo (JPG, PNG, HEIC…) ou une vidéo (MP4, MOV…).',
            'attachment.max' => 'Fichier trop lourd (50 Mo maximum).',
        ];
    }

    /**
     * Crée le message + stocke la pièce jointe éventuelle sur le disque public.
     */
    private function buildMessage(Request $request, ?int $listingId, int $receiverId): Message
    {
        $path = $type = $mime = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $mime = $file->getMimeType();
            $type = str_starts_with((string) $mime, 'video/') ? 'video' : 'image';
            $path = $file->store('messages', 'public');
        }

        return Message::create([
            'listing_id' => $listingId,
            'sender_id' => Auth::id(),
            'receiver_id' => $receiverId,
            'body' => $request->input('body'),
            'attachment_path' => $path,
            'attachment_type' => $type,
            'attachment_mime' => $mime,
        ]);
    }
}

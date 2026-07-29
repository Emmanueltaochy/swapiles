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

        $screen = $this->screenMessage($request, null, $user->id);
        if (isset($screen['stop'])) {
            return $screen['stop'];
        }

        $message = $this->buildMessage($request, null, $user->id, $screen['flag'] ?? null);

        $this->safeNotifyMessage($user, $message);

        if (($screen['flag']['kind'] ?? null) === 'payment_forced') {
            $this->afterForcedPayment($message, $user);
        }

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

        $screen = $this->screenMessage($request, $listing->id, $user->id);
        if (isset($screen['stop'])) {
            return $screen['stop'];
        }

        $message = $this->buildMessage($request, $listing->id, $user->id, $screen['flag'] ?? null);

        $this->safeNotifyMessage($user, $message);

        if (($screen['flag']['kind'] ?? null) === 'payment_forced') {
            $this->afterForcedPayment($message, $user);
        }

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
    private function buildMessage(Request $request, ?int $listingId, int $receiverId, ?array $flag = null): Message
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
            'flagged_at' => $flag ? now() : null,
            'flag_kind' => $flag['kind'] ?? null,
            'flag_reason' => $flag['reason'] ?? null,
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | Modération Partie 1 — détection par mots-clés (sans IA)
     |--------------------------------------------------------------------------
     */

    /**
     * Analyse le message AVANT création.
     *  - Paiement hors plateforme → blocage à l'envoi (Option A : « Envoyer
     *    quand même » ; Option B : non délivré) selon le flag de config.
     *  - Numéro / contact hors plateforme sur les premiers messages → simple
     *    signalement (le message part normalement).
     *
     * @return array{stop?:\Illuminate\Http\RedirectResponse, flag?:array}
     */
    private function screenMessage(Request $request, ?int $listingId, int $receiverId): array
    {
        $body = (string) $request->input('body', '');
        $payments = \App\Support\MessageModeration::detectPayment($body);

        if (! empty($payments) && (bool) config('features.moderation_keyword_block', true)) {
            $reason = 'paiement hors plateforme : ' . implode(', ', $payments);

            // Option B : le message n'est pas délivré du tout.
            if (config('features.moderation_block_mode', 'A') === 'B') {
                return ['stop' => back()->withInput()->withErrors([
                    'body' => \App\Support\MessageModeration::PAYMENT_WARNING_TITLE
                        . ". Utilise le paiement sécurisé Swap'Îles ou les espèces en main propre.",
                ])];
            }

            // Option A : blocage à l'envoi tant que l'expéditeur n'a pas confirmé.
            if (! $request->boolean('moderation_confirm')) {
                return ['stop' => back()
                    ->withInput()
                    ->with('moderation_payment_warning', $payments)];
            }

            // Confirmé (« Envoyer quand même ») → message signalé.
            return ['flag' => ['kind' => 'payment_forced', 'reason' => $reason]];
        }

        // Signalement discret d'un échange de numéro sur les premiers messages.
        if ($this->isEarlyConversation($listingId, $receiverId)
            && \App\Support\MessageModeration::detectPhone($body)) {
            return ['flag' => ['kind' => 'phone', 'reason' => 'échange de numéro / contact hors plateforme (début de conversation)']];
        }

        return [];
    }

    /** La conversation compte-t-elle moins de 3 messages (donc « au début ») ? */
    private function isEarlyConversation(?int $listingId, int $otherId): bool
    {
        $me = Auth::id();

        $query = Message::query()->where(function ($q) use ($me, $otherId) {
            $q->where(fn ($x) => $x->where('sender_id', $me)->where('receiver_id', $otherId))
                ->orWhere(fn ($x) => $x->where('sender_id', $otherId)->where('receiver_id', $me));
        });

        $listingId ? $query->where('listing_id', $listingId) : $query->whereNull('listing_id');

        return $query->count() < 3;
    }

    /**
     * Après un envoi « quand même » d'un message avec paiement hors plateforme :
     * on avertit le destinataire (bandeau + notification) et on signale à l'admin.
     */
    private function afterForcedPayment(Message $message, User $receiver): void
    {
        try {
            $url = $message->listing_id
                ? route('account.messages.show', ['listing' => $message->listing_id, 'user' => $message->sender_id], absolute: false)
                : route('account.messages.show.general', ['user' => $message->sender_id], absolute: false);

            \App\Models\Notification::create([
                'user_id' => $receiver->id,
                'type' => 'moderation_warning',
                'title' => '⚠️ Paiement hors plateforme',
                'message' => "Un message te propose un paiement hors Swap'Îles. Privilégie le paiement sécurisé ou les espèces en main propre — ne valide jamais un lien de paiement reçu par SMS.",
                'url' => $url,
            ]);

            \App\Support\AdminEvent::notify(
                'Paiement hors plateforme signalé',
                'Un message contenant un mot-clé de paiement hors plateforme (' . ($message->flag_reason ?? '—')
                    . ') a été envoyé malgré l\'avertissement. Expéditeur #' . $message->sender_id
                    . ' → destinataire #' . $message->receiver_id . '.',
                $url
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

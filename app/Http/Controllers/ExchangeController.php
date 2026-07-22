<?php

namespace App\Http\Controllers;

use App\Jobs\SendMessageReceivedEmail;
use App\Models\ExchangeProposal;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ExchangeController extends Controller
{
    public function create(Listing $listing)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('status', "Connectez-vous pour proposer un échange.");
        }

        if (Auth::id() === $listing->user_id) {
            return redirect()->route('listings.show', $listing);
        }

        if (!$this->exchangeAllowed($listing) || $listing->status !== 'published') {
            return redirect()->route('listings.show', $listing)
                ->with('status', "Cet article n'accepte plus les échanges.");
        }

        $myListings = Listing::where('user_id', Auth::id())
            ->where('status', 'published')
            ->with('images')
            ->latest()
            ->get();

        return view('exchange.create', compact('listing', 'myListings'));
    }

    public function store(Request $request, Listing $listing)
    {
        if (!Auth::check() || Auth::id() === $listing->user_id) {
            abort(403);
        }

        if (!$this->exchangeAllowed($listing) || $listing->status !== 'published') {
            return redirect()->route('listings.show', $listing)
                ->with('status', "Cet article n'accepte plus les échanges.");
        }

        $data = $request->validate([
            'mode' => ['required', 'in:listing,custom'],
            'offered_listing_id' => ['nullable', 'integer'],
            'offered_title' => ['nullable', 'string', 'max:120'],
            'offered_condition' => ['nullable', 'string', 'max:80'],
            'offered_description' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $seller = $listing->user;

        $proposal = new ExchangeProposal([
            'listing_id' => $listing->id,
            'proposer_id' => Auth::id(),
            'seller_id' => $listing->user_id,
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        if ($data['mode'] === 'listing') {
            $offered = Listing::where('id', $data['offered_listing_id'] ?? 0)
                ->where('user_id', Auth::id())
                ->first();

            if (!$offered) {
                return back()->withErrors(['offered_listing_id' => 'Choisissez une de vos annonces.'])->withInput();
            }

            $proposal->offered_listing_id = $offered->id;
            $proposal->offered_title = $offered->title;
        } else {
            if (empty($data['offered_title'])) {
                return back()->withErrors(['offered_title' => 'Donnez un nom à l’article proposé.'])->withInput();
            }

            $proposal->offered_title = $data['offered_title'];
            $proposal->offered_condition = $data['offered_condition'] ?? null;
            $proposal->offered_description = $data['offered_description'] ?? null;

            if ($request->hasFile('photo')) {
                $path = $request->file('photo')->store('exchange-proposals', 'public');
                $proposal->offered_photo_path = Storage::url($path);
            }
        }

        $proposal->save();

        // Message dans le chat (déclenche notification + e-mail existants).
        $lines = ["🔄 Proposition d'échange : « {$proposal->displayTitle()} »"];
        if ($proposal->offered_condition) {
            $lines[] = "État : {$proposal->offered_condition}";
        }
        if ($proposal->offered_description) {
            $lines[] = $proposal->offered_description;
        }
        if ($proposal->message) {
            $lines[] = "Message : {$proposal->message}";
        }
        $lines[] = "→ Retrouvez cette proposition dans la conversation pour l’accepter ou la refuser.";

        $message = Message::create([
            'listing_id' => $listing->id,
            'sender_id' => Auth::id(),
            'receiver_id' => $listing->user_id,
            'body' => implode("\n", $lines),
        ]);

        $this->notifySeller($seller->id, $message, Auth::user()?->name);

        return redirect()->route('account.messages.show', [
            'listing' => $listing,
            'user' => $seller,
        ])->with('status', "Votre proposition d'échange a été envoyée.");
    }

    public function accept(ExchangeProposal $proposal)
    {
        abort_unless(Auth::id() === $proposal->seller_id, 403);

        if ($proposal->status !== 'pending') {
            return back();
        }

        $proposal->update(['status' => 'accepted']);

        $message = Message::create([
            'listing_id' => $proposal->listing_id,
            'sender_id' => Auth::id(),
            'receiver_id' => $proposal->proposer_id,
            'body' => "✅ Votre proposition d'échange « {$proposal->displayTitle()} » a été acceptée ! Convenez ensemble de la remise en main propre.",
        ]);

        $this->notifySeller($proposal->proposer_id, $message, Auth::user()?->name);

        return back()->with('status', "Proposition d'échange acceptée.");
    }

    public function refuse(ExchangeProposal $proposal)
    {
        abort_unless(Auth::id() === $proposal->seller_id, 403);

        if ($proposal->status !== 'pending') {
            return back();
        }

        $proposal->update(['status' => 'refused']);

        $message = Message::create([
            'listing_id' => $proposal->listing_id,
            'sender_id' => Auth::id(),
            'receiver_id' => $proposal->proposer_id,
            'body' => "❌ Votre proposition d'échange « {$proposal->displayTitle()} » a été refusée.",
        ]);

        $this->notifySeller($proposal->proposer_id, $message, Auth::user()?->name);

        return back()->with('status', "Proposition d'échange refusée.");
    }

    private function exchangeAllowed(Listing $listing): bool
    {
        return (bool) ($listing->allows_exchange || $listing->listing_type === 'echange-produits');
    }

    private function notifySeller(int $recipientId, Message $message, ?string $senderName): void
    {
        try {
            Notification::create([
                'user_id' => $recipientId,
                'type' => 'exchange_proposal',
                'title' => 'Échange 🔄',
                'message' => ($senderName ?? 'Un membre') . ' concernant un échange.',
                'url' => route('account.messages.show', [
                    'listing' => $message->listing_id,
                    'user' => $message->sender_id,
                ]),
            ]);

            SendMessageReceivedEmail::dispatch($message->id, $recipientId);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

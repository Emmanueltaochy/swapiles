<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Jobs\SendSellerPublishedListingEmail;
use App\Jobs\SendListingPublishedShareEmail;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Notifications\SellerPublishedListingNotification;
use App\Support\AdminEvent;

class ListingManageController extends Controller
{
    public function create()
    {
        return view('account.listings.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateListing($request);

        $cbEnabled = $request->boolean('payment_cb') && $this->userCanReceiveOnlinePayments();
        $allowsColissimo = $cbEnabled && $request->boolean('allows_colissimo');
        $allowsHandDelivery = $request->boolean('allows_hand_delivery') || !$cbEnabled;

        if ($request->boolean('payment_cb') && ! $this->userCanReceiveOnlinePayments()) {
            return back()
                ->withErrors(['payment_cb' => 'Pour activer le paiement CB sécurisé, vous devez d’abord connecter votre compte bancaire.'])
                ->withInput();
        }

        if ($request->boolean('allows_colissimo') && ! $cbEnabled) {
            return back()
                ->withErrors(['allows_colissimo' => 'Colissimo est disponible uniquement si le paiement CB sécurisé est activé.'])
                ->withInput();
        }

        if ($allowsColissimo && ! $this->userHasCompleteAddress()) {
            return back()
                ->withErrors(['allows_colissimo' => 'Veuillez renseigner votre adresse pour activer Colissimo (elle sert d’adresse d’expédition).'])
                ->with('need_address', true)
                ->withInput();
        }

        // Vendre sur d'autres îles nécessite Colissimo (pas de main propre inter-îles).
        $alsoTerritoires = $this->extraTerritoires($data, $data['territoire']);
        if (! empty($alsoTerritoires) && ! $allowsColissimo) {
            return back()
                ->withErrors(['also_territoires' => 'Pour vendre sur d’autres îles, activez Colissimo : la remise en main propre n’est pas possible entre deux îles.'])
                ->withInput();
        }

        if ($allowsColissimo && empty($data['weight_kg'])) {
            return back()
                ->withErrors(['weight_kg' => 'Le poids du colis est obligatoire pour proposer Colissimo.'])
                ->withInput();
        }

        $listing = Listing::create([
            'user_id' => Auth::id(),
            'title' => $data['title'],
            'description' => $data['description'],
            'price' => $data['listing_type'] === 'don' ? 0 : (int) ($data['price'] ?? 0),
            'currency' => 'EUR',
            'listing_type' => $data['listing_type'],
            'allows_offers' => $request->boolean('payment_negociable') || $data['listing_type'] === 'negoce-prix',
            'allows_exchange' => $request->boolean('payment_exchange') || $data['listing_type'] === 'echange-produits',
            'status' => 'published',
            'territoire' => $data['territoire'],
            'also_territoires' => $alsoTerritoires ?: null,
            'category_level1' => $data['category_level1'],
            'category_level2' => $data['category_level2'] ?? null,
            'category_level3' => $data['category_level3'] ?? null,
            'etat' => $data['etat'] ?? null,
            'marque' => $data['marque'] ?? null,
            'taille' => $data['taille'] ?? null,
            'location_address' => $data['location_address'] ?? null,
            'hand_delivery_location' => $data['hand_delivery_location'] ?? $data['location_address'] ?? null,
            'pickup_enabled' => $allowsHandDelivery,
            'shipping_enabled' => $allowsColissimo,
            'allows_hand_delivery' => $allowsHandDelivery,
            'allows_colissimo' => $allowsColissimo,
            'requires_online_payment' => $cbEnabled,
            'shipping_price' => 0,
            'weight_kg' => $allowsColissimo ? ($data['weight_kg'] ?? null) : null,
            'views_count' => 0,
        ]);

        $this->storeImages($request, $listing);

        $this->notifyFollowersNewListing($listing);

        // E-mail au vendeur : « partagez votre annonce sur vos réseaux ».
        try {
            SendListingPublishedShareEmail::dispatch($listing->id);
        } catch (\Throwable $e) {
            report($e);
        }

        AdminEvent::notify(
            'Nouvelle annonce publiée',
            'Une nouvelle annonce vient d’être publiée : ' . $listing->title . ' par ' . (auth()->user()->name ?? 'Utilisateur'),
            route('listings.show', $listing)
        );

        return redirect()->route('listings.show', $listing)->with('status', 'Votre annonce a bien été publiée.');
    }

    public function edit(Listing $listing)
    {
        $this->authorizeOwner($listing);

        return view('account.listings.edit', compact('listing'));
    }

    public function update(Request $request, Listing $listing)
    {
        $this->authorizeOwner($listing);

        $data = $this->validateListing($request);

        $cbEnabled = $request->boolean('payment_cb') && $this->userCanReceiveOnlinePayments();
        $allowsColissimo = $cbEnabled && $request->boolean('allows_colissimo');
        $allowsHandDelivery = $request->boolean('allows_hand_delivery') || !$cbEnabled;

        if ($request->boolean('payment_cb') && ! $this->userCanReceiveOnlinePayments()) {
            return back()
                ->withErrors(['payment_cb' => 'Pour activer le paiement CB sécurisé, vous devez d’abord connecter votre compte bancaire.'])
                ->withInput();
        }

        if ($request->boolean('allows_colissimo') && ! $cbEnabled) {
            return back()
                ->withErrors(['allows_colissimo' => 'Colissimo est disponible uniquement si le paiement CB sécurisé est activé.'])
                ->withInput();
        }

        if ($allowsColissimo && ! $this->userHasCompleteAddress()) {
            return back()
                ->withErrors(['allows_colissimo' => 'Veuillez renseigner votre adresse pour activer Colissimo (elle sert d’adresse d’expédition).'])
                ->with('need_address', true)
                ->withInput();
        }

        $alsoTerritoires = $this->extraTerritoires($data, $data['territoire']);
        if (! empty($alsoTerritoires) && ! $allowsColissimo) {
            return back()
                ->withErrors(['also_territoires' => 'Pour vendre sur d’autres îles, activez Colissimo : la remise en main propre n’est pas possible entre deux îles.'])
                ->withInput();
        }

        if ($allowsColissimo && empty($data['weight_kg'])) {
            return back()
                ->withErrors(['weight_kg' => 'Le poids du colis est obligatoire pour proposer Colissimo.'])
                ->withInput();
        }

        $listing->update([
            'title' => $data['title'],
            'description' => $data['description'],
            'price' => $data['listing_type'] === 'don' ? 0 : (int) ($data['price'] ?? 0),
            'listing_type' => $data['listing_type'],
            'allows_offers' => $request->boolean('payment_negociable') || $data['listing_type'] === 'negoce-prix',
            'allows_exchange' => $request->boolean('payment_exchange') || $data['listing_type'] === 'echange-produits',
            'territoire' => $data['territoire'],
            'also_territoires' => $alsoTerritoires ?: null,
            'category_level1' => $data['category_level1'],
            'category_level2' => $data['category_level2'] ?? null,
            'category_level3' => $data['category_level3'] ?? null,
            'etat' => $data['etat'] ?? null,
            'marque' => $data['marque'] ?? null,
            'taille' => $data['taille'] ?? null,
            'location_address' => $data['location_address'] ?? null,
            'hand_delivery_location' => $data['hand_delivery_location'] ?? $data['location_address'] ?? null,
            'pickup_enabled' => $allowsHandDelivery,
            'shipping_enabled' => $allowsColissimo,
            'allows_hand_delivery' => $allowsHandDelivery,
            'allows_colissimo' => $allowsColissimo,
            'requires_online_payment' => $cbEnabled,
            'shipping_price' => 0,
            'weight_kg' => $allowsColissimo ? ($data['weight_kg'] ?? null) : null,
        ]);

        $this->storeImages($request, $listing);

        return redirect()->route('account.dashboard')->with('status', 'Annonce mise à jour.');
    }

    public function hide(Listing $listing)
    {
        $this->authorizeOwner($listing);

        $listing->update(['status' => 'draft']);

        return back()->with('status', 'Annonce masquée.');
    }

    public function markSold(Listing $listing)
    {
        $this->authorizeOwner($listing);

        $listing->update(['status' => 'sold']);

        return back()->with('status', 'Annonce marquée comme vendue.');
    }


    public function markCashPaid(Listing $listing)
    {
        $this->authorizeOwner($listing);

        \App\Models\Transaction::create([
            'listing_id' => $listing->id,
            'seller_id' => $listing->user_id,
            'buyer_id' => null,
            'amount' => (float) $listing->price,
            'commission' => 0,
            'buyer_protection_fee' => 0,
            'shipping_fee' => 0,
            'seller_amount' => (float) $listing->price,
            'currency' => 'EUR',
            'payment_method' => 'especes',
            'delivery_method' => 'hand_delivery',
            'status' => 'completed',
            'shipping_status' => 'hand_delivered',
            'completed_at' => now(),
            'received_at' => now(),
            'hand_delivery_location' => $listing->hand_delivery_location ?: $listing->location_address,
        ]);

        $listing->update(['status' => 'sold']);

        return back()->with('status', 'Paiement en espèces confirmé. L’annonce est marquée comme vendue.');
    }

    public function markExchanged(Listing $listing)
    {
        $this->authorizeOwner($listing);

        \App\Models\Transaction::create([
            'listing_id' => $listing->id,
            'seller_id' => $listing->user_id,
            'buyer_id' => null,
            'amount' => 0,
            'commission' => 0,
            'buyer_protection_fee' => 0,
            'shipping_fee' => 0,
            'seller_amount' => 0,
            'currency' => 'EUR',
            'payment_method' => 'echange',
            'delivery_method' => 'hand_delivery',
            'status' => 'completed',
            'shipping_status' => 'exchanged',
            'completed_at' => now(),
            'received_at' => now(),
            'hand_delivery_location' => $listing->hand_delivery_location ?: $listing->location_address,
        ]);

        $listing->update(['status' => 'sold']);

        return back()->with('status', 'Échange confirmé. L’annonce est marquée comme terminée.');
    }

    public function markGiven(Listing $listing)
    {
        $this->authorizeOwner($listing);

        \App\Models\Transaction::create([
            'listing_id' => $listing->id,
            'seller_id' => $listing->user_id,
            'buyer_id' => null,
            'amount' => 0,
            'commission' => 0,
            'buyer_protection_fee' => 0,
            'shipping_fee' => 0,
            'seller_amount' => 0,
            'currency' => 'EUR',
            'payment_method' => 'don',
            'delivery_method' => 'hand_delivery',
            'status' => 'completed',
            'shipping_status' => 'given',
            'completed_at' => now(),
            'received_at' => now(),
            'hand_delivery_location' => $listing->hand_delivery_location ?: $listing->location_address,
        ]);

        $listing->update(['status' => 'sold']);

        return back()->with('status', 'Don remis confirmé. L’annonce est marquée comme terminée.');
    }

    public function publish(Listing $listing)
    {
        $this->authorizeOwner($listing);

        $wasPublished = $listing->status === 'published';

        $listing->update(['status' => 'published']);

        if (!$wasPublished) {
            $this->notifyFollowersNewListing($listing);
        }

        return back()->with('status', 'Annonce remise en ligne.');
    }

    public function makeMainImage(Listing $listing, ListingImage $image)
    {
        $this->authorizeOwner($listing);

        abort_unless($image->listing_id === $listing->id, 403);

        $listing->images()->update(['order' => 1000]);

        $image->update(['order' => 0]);

        $order = 1;

        foreach ($listing->images()->where('id', '!=', $image->id)->orderBy('id')->get() as $otherImage) {
            $otherImage->update(['order' => $order]);
            $order++;
        }

        return back()->with('status', 'Photo principale mise à jour.');
    }

    public function destroyImage(Listing $listing, ListingImage $image)
    {
        $this->authorizeOwner($listing);

        abort_unless($image->listing_id === $listing->id, 403);

        if (str_starts_with($image->url, '/storage/')) {
            $path = str_replace('/storage/', '', $image->url);
            Storage::disk('public')->delete($path);
        }

        $image->delete();

        return back()->with('status', 'Photo supprimée.');
    }

    public function destroy(Listing $listing)
    {
        $this->authorizeOwner($listing);

        $listing->delete();

        return redirect()->route('account.dashboard')->with('status', 'Annonce supprimée.');
    }

    private function validateListing(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:5000'],
            'price' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'listing_type' => ['required', 'in:achat,echange-produits,don,location-vetements,negoce-prix'],
            'territoire' => ['required', 'string', 'max:80'],
            'also_territoires' => ['nullable', 'array'],
            'also_territoires.*' => ['string', 'in:La Réunion,Martinique,Guadeloupe,Guyane,Mayotte'],
            'category_level1' => ['required', 'string', 'max:80'],
            'category_level2' => ['nullable', 'string', 'max:120'],
            'category_level3' => ['nullable', 'string', 'max:120'],
            'etat' => ['nullable', 'string', 'max:80'],
            'marque' => ['nullable', 'string', 'max:120'],
            'taille' => ['nullable', 'string', 'max:50'],
            'location_address' => ['nullable', 'string', 'max:255'],
            'hand_delivery_location' => ['nullable', 'string', 'max:255'],
            'pickup_enabled' => ['nullable'],
            'shipping_enabled' => ['nullable'],
            'allows_hand_delivery' => ['nullable'],
            'allows_colissimo' => ['nullable'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.01', 'max:30'],
            'images.*' => ['nullable', 'image', 'max:5120'],
        ]);
    }

    private function userCanReceiveOnlinePayments(): bool
    {
        $user = Auth::user();

        return (bool) (
            $user
            && $user->stripe_account_id
            && $user->stripe_charges_enabled
            && $user->stripe_payouts_enabled
            && $user->stripe_details_submitted
        );
    }

    /** Îles supplémentaires choisies (hors île principale), nettoyées. */
    private function extraTerritoires(array $data, string $primary): array
    {
        return collect($data['also_territoires'] ?? [])
            ->filter()
            ->map(fn ($t) => (string) $t)
            ->reject(fn ($t) => $t === $primary)
            ->unique()
            ->values()
            ->all();
    }

    /** L'adresse d'expédition (vendeur) est complète : requise pour Colissimo. */
    private function userHasCompleteAddress(): bool
    {
        $user = Auth::user();

        return (bool) (
            $user
            && filled($user->address_line1)
            && filled($user->postal_code)
            && filled($user->city)
        );
    }

    private function storeImages(Request $request, Listing $listing): void
    {
        if (!$request->hasFile('images')) {
            return;
        }

        $currentCount = $listing->images()->count();

        foreach ($request->file('images') as $index => $image) {
            if (!$image) {
                continue;
            }

            $path = $image->store('listings/' . $listing->id, 'public');

            ListingImage::create([
                'listing_id' => $listing->id,
                'url' => Storage::url($path),
                'order' => $currentCount + $index,
            ]);
        }
    }

    private function notifyFollowersNewListing(\App\Models\Listing $listing): void
    {
        try {
            \App\Models\User::query()
                ->whereIn('id', function ($query) use ($listing) {
                    $query->select('follower_id')
                        ->from('seller_follows')
                        ->where('seller_id', $listing->user_id);
                })
                ->whereNotNull('email')
                ->chunkById(100, function ($followers) use ($listing) {
                    foreach ($followers as $follower) {
                        \App\Models\Notification::create([
                            'user_id' => $follower->id,
                            'type' => 'seller_published_listing',
                            'title' => 'Nouvelle annonce 🆕',
                            'message' => ($listing->user->name ?? 'Un vendeur que vous suivez') . ' a publié : ' . $listing->title,
                            'url' => route('listings.show', $listing, absolute: false),
                        ]);

                        SendSellerPublishedListingEmail::dispatch($listing->id, $follower->id);
                    }
                });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function authorizeOwner(Listing $listing): void
    {
        abort_unless($listing->user_id === Auth::id(), 403);
    }
}

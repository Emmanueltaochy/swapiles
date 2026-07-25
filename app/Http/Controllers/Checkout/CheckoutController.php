<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\ListingOffer;
use App\Support\AdminEvent;
use App\Support\TransactionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ColissimoRateService;
use App\Services\StripePaymentIntentService;
use App\Support\OrderPricing;

class CheckoutController extends Controller
{
    public function start(Request $request, Listing $listing)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('status', 'Connectez-vous pour finaliser votre achat.');
        }

        // Article déjà vendu / indisponible : message clair plutôt qu'une erreur brute.
        $alreadySold = $listing->status === 'sold'
            || Transaction::where('listing_id', $listing->id)
                ->whereIn('status', ['paid', 'completed'])
                ->exists();

        if ($alreadySold) {
            return redirect()->route('listings.show', $listing)
                ->with('status', "Cet article vient d'être vendu. 😔");
        }

        // Achat en ligne possible pour une vente classique ou à prix négociable.
        abort_unless(in_array($listing->listing_type, ['achat', 'negoce-prix'], true), 403);
        abort_if($listing->price <= 0, 403);

        // Le vendeur doit RÉELLEMENT pouvoir encaisser par carte (compte Stripe
        // opérationnel). Sinon on renvoie vers l'annonce (remise en main propre).
        if (! $listing->isOnlinePayable()) {
            return redirect()->route('listings.show', $listing)
                ->with('status', "Le paiement par carte n'est pas disponible pour cet article. Contactez le vendeur pour une remise en main propre.");
        }

        $offer = null;

        if ($request->filled('offer')) {
            $offer = ListingOffer::where('id', $request->input('offer'))
                ->where('listing_id', $listing->id)
                ->where('buyer_id', Auth::id())
                ->where('status', 'accepted')
                ->first();

            if (!$offer) {
                return redirect()->route('listings.show', $listing)
                    ->with('status', "Cette offre n'est plus disponible.");
            }
        }

        if ($request->isMethod('GET')) {
            return view('checkout.order', [
                'listing' => $listing,
                'offer' => $offer,
                'itemAmount' => $offer ? (int) $offer->amount : (int) $listing->price,
            ]);
        }

        $deliveryMethod = $request->input('delivery_method');

        if ($deliveryMethod === 'colissimo') {
            abort_unless(($listing->requires_online_payment ?? false) && ($listing->allows_colissimo ?? false), 403);

            $validated = $request->validate([
                'delivery_method' => ['required', 'in:colissimo,hand_delivery'],
                'colissimo_delivery_type' => ['nullable', 'in:home'],
                'buyer_full_name' => ['required', 'string', 'max:120'],
                'buyer_phone' => ['required', 'string', 'max:40'],
                'shipping_address_line1' => ['nullable', 'required_if:colissimo_delivery_type,home', 'string', 'max:255'],
                'shipping_address_line2' => ['nullable', 'string', 'max:255'],
                'shipping_postal_code' => ['required', 'string', 'max:20'],
                'shipping_city' => ['required', 'string', 'max:120'],
                'shipping_territory' => ['required', 'string', 'max:80'],
                'shipping_country' => ['nullable', 'string', 'max:80'],
            ]);
        } elseif ($deliveryMethod === 'hand_delivery') {
            abort_unless(($listing->allows_hand_delivery ?? $listing->pickup_enabled ?? true), 403);

            $validated = $request->validate([
                'delivery_method' => ['required', 'in:colissimo,hand_delivery'],
            ]);
        } else {
            abort(422, 'Choisissez un mode de remise.');
        }

        /*
         |--------------------------------------------------------------------------
         | Montant article
         |--------------------------------------------------------------------------
         | Si une offre acceptée existe, le paiement CB doit utiliser le montant
         | de l'offre, pas l'ancien prix de l'annonce.
         */
        $itemAmount = $offer
            ? (float) $offer->amount
            : (float) $listing->price;

        /*
         |--------------------------------------------------------------------------
         | Livraison
         |--------------------------------------------------------------------------
         | 0 en remise en main propre. En Colissimo : tarif réel (aucun faux prix).
         */
        $shippingEuros = 0.0;

        if ($deliveryMethod === 'colissimo') {
            try {
                $shippingEuros = (float) app(ColissimoRateService::class)->calculateForListing($listing, $validated);
            } catch (\Throwable $e) {
                report($e);

                return back()
                    ->withInput()
                    ->withErrors([
                        'delivery_method' => "Le tarif Colissimo réel n'a pas pu être calculé pour le moment. Aucun faux prix n’a été appliqué.",
                    ]);
            }
        }

        /*
         |--------------------------------------------------------------------------
         | SOURCE DE VÉRITÉ UNIQUE DES MONTANTS (centimes)
         |--------------------------------------------------------------------------
         | protection = clamp(round(prix*10%, 2, HALF_UP), 0,50 €, 15,00 €)
         | commission vendeur = 0  -> le vendeur reçoit EXACTEMENT le prix affiché
         | total = prix + protection + livraison
         |
         | Ce même objet alimente l'affichage ET le montant du PaymentIntent :
         | le montant débité est TOUJOURS égal au « Total à payer » affiché.
         */
        $pricing = OrderPricing::fromEuros($itemAmount, $shippingEuros);

        $transaction = Transaction::create([
            'listing_id' => $listing->id,
            'listing_offer_id' => $offer?->id,
            'seller_id' => $listing->user_id,
            'buyer_id' => Auth::id(),
            'amount' => $pricing->totalEuros(),
            'commission' => 0,
            'platform_commission' => 0,
            'buyer_protection_fee' => $pricing->protectionEuros(),
            'shipping_fee' => $pricing->shippingEuros(),
            'seller_amount' => $pricing->sellerEuros(),
            'currency' => 'EUR',
            'payment_method' => 'cb',
            'delivery_method' => $deliveryMethod,
            'buyer_full_name' => $validated['buyer_full_name'] ?? null,
            'buyer_phone' => $validated['buyer_phone'] ?? null,
            'shipping_address_line1' => $validated['shipping_address_line1'] ?? null,
            'shipping_address_line2' => $validated['shipping_address_line2'] ?? null,
            'shipping_postal_code' => $validated['shipping_postal_code'] ?? null,
            'shipping_city' => $validated['shipping_city'] ?? null,
            'shipping_country' => $validated['shipping_country'] ?? null,
            'hand_delivery_location' => $deliveryMethod === 'hand_delivery'
                ? ($listing->hand_delivery_location ?: $listing->location_address)
                : null,
            'shipping_status' => 'pending',
            'status' => 'pending',
        ]);

        $metadata = [
            'transaction_id' => $transaction->id,
            'listing_id' => $listing->id,
            'listing_offer_id' => $offer?->id,
            'seller_id' => $listing->user_id,
            'buyer_id' => Auth::id(),
            'delivery_method' => $deliveryMethod,
        ];

        // On n'écrit colissimo_delivery_type QUE pour une expédition (sinon
        // métadonnées contradictoires « main propre » + « à expédier »).
        if ($deliveryMethod === 'colissimo') {
            $metadata['colissimo_delivery_type'] = $validated['colissimo_delivery_type'] ?? 'home';
        }

        // Montant Stripe = total exact en centimes entiers (== total affiché).
        // Item 9 : Customer réutilisé + reçu e-mail à l'acheteur, carte + Link.
        $buyer = Auth::user();
        $piService = app(StripePaymentIntentService::class);

        $paymentIntent = $piService->create(
            $pricing->totalCents(),
            $metadata,
            [
                'customer' => $piService->resolveCustomerId($buyer),
                'receipt_email' => $buyer->email,
            ],
        );

        $transaction->update([
            'stripe_payment_intent_id' => $paymentIntent->id,
        ]);

        return view('checkout.payment', [
            'listing' => $listing,
            'transaction' => $transaction,
            'clientSecret' => $paymentIntent->client_secret,
            'itemAmount' => $pricing->itemEuros(),
            'buyerProtectionFee' => $pricing->protectionEuros(),
            'shippingFee' => $pricing->shippingEuros(),
            'totalAmount' => $pricing->totalEuros(),
            'sellerAmount' => $pricing->sellerEuros(),
            'deliveryMethod' => $deliveryMethod,
        ]);
    }

    public function success(Transaction $transaction)
    {
        abort_unless(
            auth()->id() === $transaction->buyer_id || auth()->id() === $transaction->seller_id,
            403
        );

        try {
            // Transition « payé » unique et idempotente : si le webhook Stripe
            // a déjà traité ce paiement, markPaidOnce renvoie false et ne
            // recrée NI notification NI e-mail (fin du doublon de notification).
            $justClaimed = TransactionPayment::markPaidOnce($transaction);

            if ($justClaimed) {
                $freshTransaction = $transaction->fresh(['listing', 'seller', 'buyer']);

                AdminEvent::notify(
                    'Nouvelle vente validée',
                    'Vente de ' . number_format((float) $freshTransaction->amount, 2, ',', ' ') . ' € : ' . ($freshTransaction->listing->title ?? 'Annonce') . ' | vendeur : ' . ($freshTransaction->seller->name ?? '-') . ' | acheteur : ' . ($freshTransaction->buyer->name ?? '-'),
                    route('account.transactions.show', $freshTransaction)
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $redirect = redirect()
            ->route('account.transactions.show', $transaction)
            ->with('status', 'Paiement confirmé. Votre achat est bien enregistré.');

        // Événement de conversion Pixel/GA : côté ACHETEUR, une seule fois par
        // session, indépendamment de qui (page succès ou webhook) a marqué payé.
        // Sinon la conversion serait perdue quand le webhook gagne la course.
        $pixelKey = 'pixel_purchase_' . $transaction->id;
        if (auth()->id() === $transaction->buyer_id
            && $transaction->fresh()->status === 'paid'
            && !session()->get($pixelKey, false)) {
            session()->put($pixelKey, true);
            $redirect->with('pixel_event', [
                'event' => 'Purchase',
                'params' => [
                    'value' => round((float) $transaction->amount, 2),
                    'currency' => 'EUR',
                    'content_name' => $transaction->listing->title ?? 'Article',
                    'content_ids' => [$transaction->listing_id],
                    'content_type' => 'product',
                ],
            ]);
        }

        return $redirect;
    }

    public function cancel(Transaction $transaction)
    {
        abort_unless(auth()->id() === $transaction->buyer_id, 403);

        if (!$transaction->listing) {
            return redirect()->route('home')
                ->with('status', 'Paiement annulé.');
        }

        return redirect()
            ->route('listings.show', $transaction->listing)
            ->with('status', 'Paiement annulé. Vous pouvez réessayer à tout moment.');
    }
}

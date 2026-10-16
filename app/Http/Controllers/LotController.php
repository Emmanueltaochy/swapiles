<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\Lot;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StripePaymentIntentService;
use App\Support\ColissimoPricing;
use App\Support\Lots;
use App\Support\OrderPricing;
use App\Support\Portefeuille;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lots : « j'achète plusieurs articles du même dressing ». Un seul paiement,
 * une seule livraison, la remise du vendeur ; chaque article garde sa vente.
 */
class LotController extends Controller
{
    public function ajouter(Request $request, Listing $listing)
    {
        $erreur = Lots::ajouter($request->user(), $listing);

        if ($erreur) {
            return back()->with('status', $erreur);
        }

        $n = Lots::articles($request->user(), $listing->user_id)->count();

        return back()->with('status', '🛍️ Ajouté au lot (' . $n . ' article' . ($n > 1 ? 's' : '') . ' chez ' . ($listing->user->name ?? 'ce vendeur') . ').');
    }

    public function retirer(Request $request, Listing $listing)
    {
        Lots::retirer($request->user(), $listing);

        return back()->with('status', 'Article retiré du lot.');
    }

    public function show(Request $request, User $vendeur)
    {
        $articles = Lots::articles($request->user(), $vendeur);

        if ($articles->isEmpty()) {
            return redirect()->route('profiles.show', $vendeur)
                ->with('status', 'Votre lot chez ' . $vendeur->name . ' est vide : ajoutez des articles depuis son dressing.');
        }

        return view('lots.show', $this->donneesPage($request->user(), $vendeur, $articles));
    }

    public function payer(Request $request, User $vendeur)
    {
        $acheteur = $request->user();
        $articles = Lots::articles($acheteur, $vendeur);

        if ($articles->count() < 2) {
            return redirect()->route('lots.show', $vendeur)
                ->with('status', 'Un lot contient au moins 2 articles. Pour un seul article, utilisez « Acheter ».');
        }

        foreach ($articles as $article) {
            if (Lots::reservePourUnAutre($article, $acheteur) || \App\Support\LiveService::reservePourUnAutre($article, $acheteur)) {
                return redirect()->route('lots.show', $vendeur)
                    ->with('status', '« ' . $article->title . ' » est en cours d’achat par quelqu’un d’autre : retirez-le ou réessayez dans quelques minutes.');
            }
        }

        $modes = Lots::modesCommuns($articles);
        $methode = $request->input('delivery_method');
        $validated = $this->validerLivraison($request, $methode, $modes);
        if ($validated instanceof \Illuminate\Http\RedirectResponse) {
            return $validated;
        }

        /*
         | Montants (centimes). Remise du vendeur sur les articles ; protection
         | acheteur calculée UNE fois sur le total remisé ; une seule livraison.
         */
        $prix = $articles->map(fn ($a) => (int) round((float) $a->price * 100))->all();
        $pct = Lots::remisePct($vendeur, count($prix));
        $remises = Lots::prixRemises($prix, $pct);

        $livraison = 0;
        if ($methode === 'colissimo') {
            try {
                $destination = $validated['shipping_territory']
                    ?? ColissimoPricing::territoryFromPostalCode($validated['shipping_postal_code'] ?? '');
                $livraison = (int) round(ColissimoPricing::calculate(
                    $articles->first()->territoire ?: 'La Réunion', $destination, $modes['poids']
                )['shipping_fee'] * 100);
            } catch (\Throwable $e) {
                report($e);

                return back()->withInput()->withErrors(['delivery_method' => 'Le tarif Colissimo n’a pas pu être calculé pour le moment.']);
            }
        }

        $fraisRelais = 0;
        $partCommercant = 0.0;
        if ($methode === 'relay') {
            $fraisRelais = (int) round(max(0.0, (float) config('pricing.relay_fee', 3.00)) * 100);
            $partCommercant = min($fraisRelais / 100, max(0.0, (float) config('pricing.relay_merchant_fee', 1.00)));
        }

        $pricing = OrderPricing::make(array_sum($remises), $livraison, $fraisRelais);
        if (\App\Support\ParrainageService::protectionOfferte($acheteur)) {
            $pricing = $pricing->sansProtection();
        }

        $piService = app(StripePaymentIntentService::class);
        if ($request->boolean('utiliser_credit', true)) {
            // Un crédit réservé par un paiement abandonné depuis 15 min est d'abord rendu.
            Transaction::where('buyer_id', $acheteur->id)->where('status', 'pending')->where('credit_utilise', '>', 0)
                ->where('created_at', '<', now()->subMinutes(15))->get()
                ->each(function (Transaction $ancienne) use ($piService) {
                    if ($ancienne->stripe_payment_intent_id) {
                        $piService->annuler($ancienne->stripe_payment_intent_id);
                    }
                    $ancienne->update(['status' => 'cancelled']);
                });

            $solde = Portefeuille::solde($acheteur);
            if ($solde > 0) {
                $pricing = $pricing->avecCredit($solde);
            }
        }

        // Répartition article par article (chaque vente garde ses montants).
        $protections = Lots::repartir($pricing->protectionCents(), $remises);
        $offertes = Lots::repartir($pricing->protectionOfferteCents(), $remises);
        $credits = Lots::repartir($pricing->creditCents(), array_map(fn ($r, $p) => $r + $p, $remises, $protections));
        $codeRetrait = $methode === 'relay' ? $this->codeRetrait() : null;

        [$lot, $transactions] = DB::transaction(function () use (
            $acheteur, $vendeur, $articles, $pct, $prix, $remises, $protections, $offertes, $credits,
            $livraison, $fraisRelais, $partCommercant, $methode, $validated, $codeRetrait
        ) {
            $lot = Lot::create([
                'buyer_id' => $acheteur->id,
                'seller_id' => $vendeur->id,
                'nb_articles' => $articles->count(),
                'remise_pct' => $pct,
                'remise_montant' => (array_sum($prix) - array_sum($remises)) / 100,
            ]);

            $transactions = $articles->values()->map(function (Listing $article, int $i) use (
                $lot, $acheteur, $vendeur, $remises, $protections, $offertes, $credits,
                $livraison, $fraisRelais, $partCommercant, $methode, $validated, $codeRetrait
            ) {
                $principal = $i === 0; // la 1re vente porte la livraison
                $port = $principal ? $livraison : 0;
                $relais = $principal ? $fraisRelais : 0;
                $aPayer = $remises[$i] + $protections[$i] + $port + $relais - $credits[$i];

                return Transaction::create([
                    'listing_id' => $article->id,
                    'lot_id' => $lot->id,
                    'seller_id' => $vendeur->id,
                    'buyer_id' => $acheteur->id,
                    'amount' => $aPayer / 100,
                    'commission' => 0,
                    'platform_commission' => 0,
                    'buyer_protection_fee' => $protections[$i] / 100,
                    'protection_offerte' => $offertes[$i] / 100,
                    'credit_utilise' => $credits[$i] / 100,
                    'shipping_fee' => $port / 100,
                    'seller_amount' => $remises[$i] / 100,
                    'currency' => 'EUR',
                    'payment_method' => 'cb',
                    'delivery_method' => $methode,
                    'buyer_full_name' => $validated['buyer_full_name'] ?? null,
                    'buyer_phone' => $validated['buyer_phone'] ?? null,
                    'shipping_address_line1' => $validated['shipping_address_line1'] ?? null,
                    'shipping_address_line2' => $validated['shipping_address_line2'] ?? null,
                    'shipping_postal_code' => $validated['shipping_postal_code'] ?? null,
                    'shipping_city' => $validated['shipping_city'] ?? null,
                    'shipping_country' => $validated['shipping_country'] ?? null,
                    'hand_delivery_location' => $methode === 'hand_delivery'
                        ? ($article->hand_delivery_location ?: $article->location_address)
                        : null,
                    'relay_point_id' => $methode === 'relay' ? (int) $validated['relay_point_id'] : null,
                    'relay_fee' => $relais / 100,
                    'relay_merchant_fee' => $principal ? $partCommercant : 0,
                    'relay_status' => $methode === 'relay' ? 'awaiting_deposit' : null,
                    'relay_pickup_code' => $codeRetrait,
                    'shipping_status' => 'pending',
                    'status' => 'pending',
                ]);
            });

            return [$lot, $transactions];
        });

        // Crédit du portefeuille réservé vente par vente (rendu si annulée).
        foreach ($transactions as $i => $transaction) {
            if ($credits[$i] > 0 && Portefeuille::utiliser($acheteur, $credits[$i], $transaction) !== $credits[$i]) {
                // Le solde a bougé entre-temps : on annule proprement et on recommence.
                $transactions->each(fn (Transaction $t) => $t->update(['status' => 'cancelled']));

                return redirect()->route('lots.show', $vendeur)
                    ->with('status', 'Votre crédit a changé entre-temps : vérifiez le total et réessayez.');
            }
        }

        $paymentIntent = $piService->create(
            $pricing->aPayerCents(),
            [
                'lot_id' => $lot->id,
                'transaction_id' => $transactions->first()->id,
                'transaction_ids' => $transactions->pluck('id')->implode(','),
                'seller_id' => $vendeur->id,
                'buyer_id' => $acheteur->id,
                'delivery_method' => $methode,
            ],
            [
                'customer' => $piService->resolveCustomerId($acheteur),
                'receipt_email' => $acheteur->email,
            ],
        );

        Transaction::where('lot_id', $lot->id)->update(['stripe_payment_intent_id' => $paymentIntent->id]);

        Log::info('[checkout-pi] lot created', [
            'lot_id' => $lot->id,
            'payment_intent' => $paymentIntent->id,
            'articles' => $articles->count(),
            'remise_pct' => $pct,
            'a_payer_eur' => $pricing->aPayerEuros(),
            'buyer_id' => $acheteur->id,
        ]);

        $principal = $transactions->first()->fresh();

        return view('checkout.payment', [
            'listing' => $articles->first(),
            'transaction' => $principal,
            'clientSecret' => $paymentIntent->client_secret,
            'itemAmount' => array_sum($prix) / 100,
            'buyerProtectionFee' => $pricing->protectionEuros(),
            'shippingFee' => $pricing->shippingEuros(),
            'relayFee' => $pricing->relayFeeEuros(),
            'totalAmount' => $pricing->aPayerEuros(),
            'sellerAmount' => $pricing->sellerEuros(),
            'deliveryMethod' => $methode,
            'protectionOfferte' => $pricing->protectionOfferteEuros(),
            'creditUtilise' => $pricing->creditEuros(),
            'lotArticles' => $articles,
            'lotRemise' => (array_sum($prix) - array_sum($remises)) / 100,
            'lotRemisePct' => $pct,
            'lotVendeur' => $vendeur,
        ]);
    }

    /** Données de la page du lot (récapitulatif + choix de la livraison). */
    private function donneesPage(User $acheteur, User $vendeur, $articles): array
    {
        $prix = $articles->map(fn ($a) => (int) round((float) $a->price * 100))->all();
        $pct = Lots::remisePct($vendeur, count($prix));
        $remises = Lots::prixRemises($prix, $pct);
        $protectionOfferte = \App\Support\ParrainageService::protectionOfferte($acheteur);
        $estimation = OrderPricing::make(array_sum($remises));

        return [
            'vendeur' => $vendeur,
            'articles' => $articles,
            'modes' => Lots::modesCommuns($articles),
            'sousTotal' => array_sum($prix) / 100,
            'remisePct' => $pct,
            'remise' => (array_sum($prix) - array_sum($remises)) / 100,
            'protection' => $protectionOfferte ? 0 : $estimation->protectionEuros(),
            'protectionOfferte' => $protectionOfferte ? $estimation->protectionEuros() : 0,
            'creditDisponible' => Portefeuille::solde($acheteur),
            'prochainPalier' => $this->prochainPalier($vendeur, count($prix)),
        ];
    }

    /** « Ajoutez 1 article pour −10 % » */
    private function prochainPalier(User $vendeur, int $nombre): ?string
    {
        $pct = (int) ($vendeur->lot_remise_pct ?? 0);
        $min = max(2, (int) ($vendeur->lot_remise_min ?? 2));
        if ($pct <= 0 || $nombre >= $min) {
            return null;
        }
        $manque = $min - $nombre;

        return 'Ajoutez ' . $manque . ' article' . ($manque > 1 ? 's' : '') . ' de ce dressing pour −' . $pct . ' % sur tout le lot.';
    }

    /** Mêmes règles que l'achat d'un article, appliquées à tout le lot. */
    private function validerLivraison(Request $request, ?string $methode, array $modes): array|\Illuminate\Http\RedirectResponse
    {
        if ($methode === 'colissimo') {
            if (! $modes['colissimo']) {
                return back()->withErrors(['delivery_method' => 'Colissimo n’est pas possible pour ce lot.']);
            }

            return $request->validate([
                'delivery_method' => ['required', 'in:colissimo'],
                'buyer_full_name' => ['required', 'string', 'max:120'],
                'buyer_phone' => ['required', 'string', 'max:40'],
                'shipping_address_line1' => ['required', 'string', 'max:255'],
                'shipping_address_line2' => ['nullable', 'string', 'max:255'],
                'shipping_postal_code' => ['required', 'string', 'max:20'],
                'shipping_city' => ['required', 'string', 'max:120'],
                'shipping_territory' => ['required', 'string', 'max:80'],
                'shipping_country' => ['nullable', 'string', 'max:80'],
            ], [
                'buyer_full_name.required' => 'Le nom complet est obligatoire pour l’expédition.',
                'buyer_phone.required' => 'Le numéro de téléphone est obligatoire pour l’expédition.',
                'shipping_address_line1.required' => 'L’adresse est obligatoire pour la livraison.',
                'shipping_postal_code.required' => 'Le code postal est obligatoire.',
                'shipping_city.required' => 'La ville est obligatoire.',
                'shipping_territory.required' => 'Le territoire de livraison est obligatoire.',
            ]);
        }

        if ($methode === 'relay') {
            $data = $request->validate([
                'delivery_method' => ['required', 'in:relay'],
                'relay_point_id' => ['required', 'integer'],
            ], ['relay_point_id.required' => 'Choisis un point relais pour retirer ton colis.']);

            if (! $modes['relay']->firstWhere('id', (int) $data['relay_point_id'])) {
                return back()->withInput()->withErrors(['relay_point_id' => 'Ce point relais n’est pas disponible pour tout le lot.']);
            }

            return $data;
        }

        if ($methode === 'hand_delivery') {
            if (! $modes['hand_delivery']) {
                return back()->withErrors(['delivery_method' => 'La remise en main propre n’est pas possible pour tout le lot.']);
            }

            return ['delivery_method' => 'hand_delivery'];
        }

        return back()->withErrors(['delivery_method' => 'Choisissez un mode de livraison.']);
    }

    private function codeRetrait(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\SendListingInterestSellerEmail;
use App\Models\Listing;
use App\Models\ListingInterest;
use App\Models\Notification;
use App\Support\Territoires;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ListingInterestController extends Controller
{
    /** L'annonce est-elle livrable inter-îles (Colissimo + CB en ligne) ? */
    public static function isShippable(Listing $listing): bool
    {
        return (bool) ($listing->requires_online_payment && $listing->allows_colissimo);
    }

    public function store(Request $request, Listing $listing)
    {
        $user = Auth::user();

        // Garde-fous : annonce en ligne, pas la sienne, acheteur d'une autre île,
        // et vendeur SANS Colissimo (sinon l'achat est déjà possible).
        abort_unless($listing->status === 'published', 404);

        if ($listing->user_id === $user->id) {
            return back()->with('status', "C'est votre propre annonce.");
        }

        if (! $user->territoire || $user->territoire === $listing->territoire) {
            return back()->with('status', "Cette demande concerne les articles d'une autre île.");
        }

        if (self::isShippable($listing)) {
            return back()->with('status', 'Ce produit est déjà disponible en livraison, vous pouvez l’acheter directement.');
        }

        $interest = ListingInterest::firstOrNew([
            'listing_id' => $listing->id,
            'buyer_id' => $user->id,
        ]);

        $isNew = ! $interest->exists;

        $interest->fill([
            'seller_id' => $listing->user_id,
            'buyer_territoire' => $user->territoire,
        ])->save();

        // On ajoute le produit aux favoris de l'acheteur (pour le retrouver).
        try {
            $user->favorites()->syncWithoutDetaching([$listing->id]);
        } catch (\Throwable $e) {
            report($e);
        }

        // On prévient le vendeur (une seule fois, à la première demande).
        if ($isNew && $listing->user_id) {
            // 1) Message prérempli dans la messagerie (de l'acheteur vers le vendeur).
            try {
                \App\Models\Message::create([
                    'listing_id' => $listing->id,
                    'sender_id' => $user->id,
                    'receiver_id' => $listing->user_id,
                    'body' => 'Bonjour, je suis intéressé(e) par votre article « ' . $listing->title . ' » '
                        . 'mais je suis à ' . Territoires::display($user->territoire) . ' et la livraison n’est pas activée. '
                        . 'Pouvez-vous activer la livraison Colissimo (paiement CB en ligne) pour que je puisse l’acheter ? Merci ! 🙏',
                ]);
            } catch (\Throwable $e) {
                report($e);
            }

            // 2) Notification in-app + e-mail au vendeur (call-to-action Colissimo).
            try {
                Notification::create([
                    'user_id' => $listing->user_id,
                    'type' => 'listing_interest',
                    'title' => 'Un acheteur veut votre article 🌍',
                    'message' => 'Un acheteur de ' . Territoires::display($user->territoire)
                        . ' est intéressé par « ' . $listing->title . ' » mais ne peut pas l’acheter : activez Colissimo (CB en ligne) pour vendre à toutes les îles.',
                    'url' => route('account.listings.edit', $listing, absolute: false),
                ]);

                SendListingInterestSellerEmail::dispatch($listing->id, $user->id, $user->territoire);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', $isNew
            ? '✅ Votre demande de livraison a été envoyée au vendeur (message + e-mail) ! Le produit est ajouté à vos favoris — vous serez notifié dès qu’il sera livrable.'
            : 'Vous avez déjà demandé la livraison. Le produit est dans vos favoris, vous serez notifié dès qu’il sera livrable.');
    }
}

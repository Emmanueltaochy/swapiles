<?php

namespace App\Support;

use App\Models\Favorite;
use App\Models\Listing;
use App\Models\ListingOffer;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * « Envoyer une offre à ceux qui ont aimé » : le vendeur propose une
 * réduction, valable 48 h, à toutes les personnes qui ont mis son article en
 * favori. Ce sont les acheteurs les plus intéressés : c'est le meilleur
 * déclencheur de vente.
 */
class OffreFavoris
{
    public const REDUCTIONS = [5, 10, 15, 20, 25, 30];

    public const DUREE_HEURES = 48;

    /** Une offre par annonce tous les 3 jours, pour ne pas lasser. */
    public const DELAI_JOURS = 3;

    /** Les membres qui ont mis l'annonce en favori (hors vendeur, bannis, bloqués). */
    public static function destinataires(Listing $listing): Collection
    {
        $vendeur = $listing->user;
        $exclus = $vendeur ? array_merge(
            [$vendeur->id],
            $vendeur->blockedUsers()->pluck('users.id')->all(),
            $vendeur->blockedByUsers()->pluck('users.id')->all(),
        ) : [];

        return User::query()
            ->whereIn('id', Favorite::where('listing_id', $listing->id)->select('user_id'))
            ->whereNotIn('id', $exclus ?: [0])
            ->where(fn ($query) => $query->whereNull('is_banned')->orWhere('is_banned', false))
            ->get();
    }

    /** Prix réduit en euros entiers (arrondi en faveur de l'acheteur), toujours sous le prix. */
    public static function prix(Listing $listing, int $pct): int
    {
        $prix = (float) $listing->price;

        return max(1, min((int) ceil($prix) - 1, (int) floor($prix * (100 - $pct) / 100)));
    }

    /** Pourquoi on ne peut pas envoyer d'offre maintenant (ou null). */
    public static function empechement(Listing $listing): ?string
    {
        return match (true) {
            $listing->status !== 'published' => 'L’annonce doit être en ligne.',
            ! in_array($listing->listing_type, ['achat', 'negoce-prix'], true) || (float) $listing->price < 2 => 'Il faut un prix d’au moins 2 € pour proposer une réduction.',
            ! $listing->isOnlinePayable() => 'Activez le paiement en ligne sur cette annonce pour envoyer une offre.',
            $listing->offre_favoris_at && $listing->offre_favoris_at->gt(now()->subDays(self::DELAI_JOURS)) => 'Une offre a déjà été envoyée récemment : vous pourrez en renvoyer une le ' . $listing->offre_favoris_at->copy()->addDays(self::DELAI_JOURS)->locale('fr')->translatedFormat('l j F') . '.',
            default => null,
        };
    }

    /** Envoie l'offre ; renvoie le nombre de personnes prévenues. */
    public static function envoyer(Listing $listing, int $pct): int
    {
        $prix = self::prix($listing, $pct);
        $expire = now()->addHours(self::DUREE_HEURES);
        $vendeur = $listing->user;
        $n = 0;

        foreach (self::destinataires($listing) as $acheteur) {
            // Une seule offre spéciale en cours par acheteur et par annonce.
            ListingOffer::where('listing_id', $listing->id)->where('buyer_id', $acheteur->id)
                ->where('source', 'favoris')->where('status', 'accepted')->update(['status' => 'expired']);

            ListingOffer::create([
                'listing_id' => $listing->id,
                'buyer_id' => $acheteur->id,
                'seller_id' => $listing->user_id,
                'amount' => $prix,
                'status' => 'accepted',
                'source' => 'favoris',
                'expire_at' => $expire,
                'message' => 'Offre spéciale pour les personnes qui ont aimé cet article (−' . $pct . ' %).',
            ]);

            Notification::create([
                'user_id' => $acheteur->id,
                'type' => 'favorite_offer',
                'title' => '🎁 Offre spéciale sur un de vos favoris',
                'message' => ($vendeur->name ?? 'Le vendeur') . ' vous propose « ' . $listing->title . ' » à ' . $prix . ' € au lieu de '
                    . number_format((float) $listing->price, 0, ',', ' ') . ' €, pendant 48 h.',
                'url' => route('listings.show', $listing, absolute: false),
            ]);
            $n++;
        }

        $listing->forceFill(['offre_favoris_at' => now()])->save();

        return $n;
    }

    /** L'offre spéciale en cours pour cet acheteur sur cette annonce, s'il y en a une. */
    public static function offreActive(Listing $listing, ?User $acheteur): ?ListingOffer
    {
        if (! $acheteur) {
            return null;
        }

        return ListingOffer::where('listing_id', $listing->id)->where('buyer_id', $acheteur->id)
            ->where('status', 'accepted')->valide()->latest('id')->first();
    }
}

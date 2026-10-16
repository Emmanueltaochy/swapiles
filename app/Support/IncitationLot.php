<?php

namespace App\Support;

use App\Models\Listing;
use App\Models\User;

/**
 * Le message « vendez en lot » à montrer à un vendeur, selon où il en est :
 * - paiement en ligne pas activé sur ses annonces → l'activer (condition des lots) ;
 * - activé mais sans remise → proposer une remise lot ;
 * - remise active → simple rappel discret.
 */
class IncitationLot
{
    /** @return array{etat: string, annonces: int, cb: int}|null */
    public static function pour(?User $vendeur): ?array
    {
        if (! $vendeur) {
            return null;
        }

        $annonces = Listing::where('user_id', $vendeur->id)->where('status', 'published')
            ->whereIn('listing_type', ['achat', 'negoce-prix'])->where('price', '>', 0);
        $total = (clone $annonces)->count();
        if ($total === 0) {
            return null;
        }
        $cb = (clone $annonces)->where('requires_online_payment', true)->count();

        $etat = match (true) {
            $cb === 0 => 'activer_cb',
            (int) $vendeur->lot_remise_pct === 0 => 'proposer_remise',
            default => 'remise_active',
        };

        return ['etat' => $etat, 'annonces' => $total, 'cb' => $cb];
    }

    /** Active le paiement en ligne sur toutes les annonces vendables du vendeur. */
    public static function activerPaiementEnLigne(User $vendeur): int
    {
        return Listing::where('user_id', $vendeur->id)->whereIn('status', ['published', 'vacances'])
            ->whereIn('listing_type', ['achat', 'negoce-prix'])->where('price', '>', 0)
            ->where(fn ($query) => $query->where('requires_online_payment', false)->orWhereNull('requires_online_payment'))
            ->update(['requires_online_payment' => true]);
    }
}

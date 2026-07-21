<?php

namespace App\Services;

use App\Models\Listing;
use App\Support\ColissimoPricing;

class ColissimoRateService
{
    public function calculateForListing(Listing $listing, array $address): float
    {
        $destination = $address['shipping_territory']
            ?? ColissimoPricing::territoryFromPostalCode($address['shipping_postal_code'] ?? '');

        return ColissimoPricing::calculate(
            $listing->territoire ?: 'La Réunion',
            $destination,
            (float) ($listing->weight_kg ?: 0.5)
        )['shipping_fee'];
    }
}

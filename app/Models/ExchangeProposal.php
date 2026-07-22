<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeProposal extends Model
{
    protected $fillable = [
        'listing_id',
        'proposer_id',
        'seller_id',
        'offered_listing_id',
        'offered_title',
        'offered_condition',
        'offered_description',
        'offered_photo_path',
        'message',
        'status',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function offeredListing()
    {
        return $this->belongsTo(Listing::class, 'offered_listing_id');
    }

    public function proposer()
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * URL de la photo proposée : soit celle de l'annonce sélectionnée,
     * soit la photo libre uploadée.
     */
    public function photoUrl(): ?string
    {
        if ($this->offered_listing_id && $this->offeredListing) {
            return optional($this->offeredListing->images()->orderBy('order')->first())->url;
        }

        return $this->offered_photo_path;
    }

    public function displayTitle(): string
    {
        if ($this->offered_listing_id && $this->offeredListing) {
            return $this->offeredListing->title;
        }

        return $this->offered_title ?: 'Article proposé';
    }
}

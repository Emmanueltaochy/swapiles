<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingInterest extends Model
{
    protected $fillable = [
        'listing_id', 'buyer_id', 'seller_id', 'buyer_territoire', 'notified_buyer_at',
    ];

    protected $casts = [
        'notified_buyer_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}

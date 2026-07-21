<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingOffer extends Model
{
    protected $fillable = [
        'listing_id',
        'buyer_id',
        'seller_id',
        'amount',
        'status',
        'message',
    ];

    public function listing() { return $this->belongsTo(Listing::class); }
    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
}

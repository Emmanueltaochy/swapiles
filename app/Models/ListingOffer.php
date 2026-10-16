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
        'expire_at',
        'source',
    ];

    protected $casts = ['expire_at' => 'datetime'];

    /** Encore utilisable : pas d'échéance, ou échéance pas encore passée. */
    public function scopeValide($query)
    {
        return $query->where(fn ($query) => $query->whereNull('expire_at')->orWhere('expire_at', '>', now()));
    }

    public function listing() { return $this->belongsTo(Listing::class); }
    public function buyer() { return $this->belongsTo(User::class, 'buyer_id'); }
    public function seller() { return $this->belongsTo(User::class, 'seller_id'); }
}

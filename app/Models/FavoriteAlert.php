<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FavoriteAlert extends Model
{
    protected $fillable = [
        'user_id',
        'listing_id',
        'type',
        'old_price',
        'new_price',
        'read_at',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

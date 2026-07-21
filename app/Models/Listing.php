<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sharetribe_id',
        'user_id',
        'title',
        'description',
        'price',
        'currency',
        'listing_type',
        'allows_offers',
        'allows_exchange',
        'status',
        'territoire',
        'category_level1',
        'category_level2',
        'category_level3',
        'etat',
        'marque',
        'taille',
        'couleurs',
        'location_address',
        'hand_delivery_location',
        'pickup_enabled',
        'shipping_enabled',
        'allows_hand_delivery',
        'allows_colissimo',
        'requires_online_payment',
        'shipping_price',
        'weight_kg',
        'views_count',
    ];

    protected $casts = [
        'couleurs' => 'array',
        'pickup_enabled' => 'boolean',
        'shipping_enabled' => 'boolean',
        'allows_hand_delivery' => 'boolean',
        'allows_colissimo' => 'boolean',
        'allows_offers' => 'boolean',
        'allows_exchange' => 'boolean',
        'requires_online_payment' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(ListingImage::class)->orderBy('order');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function favoritedBy()
    {
        return $this->belongsToMany(User::class, 'favorites')
            ->withTimestamps();
    }
}

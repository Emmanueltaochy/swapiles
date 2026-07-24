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
        'also_territoires',
        'category_level1',
        'category_level2',
        'category_level3',
        'etat',
        'marque',
        'taille',
        'couleurs',
        'location_address',
        'hand_delivery_location',
        'pickup_city',
        'pickup_postal_code',
        'pickup_enabled',
        'shipping_enabled',
        'allows_hand_delivery',
        'allows_colissimo',
        'requires_online_payment',
        'shipping_price',
        'weight_kg',
        'views_count',
        'photoless_hidden_at',
    ];

    protected $casts = [
        'couleurs' => 'array',
        'also_territoires' => 'array',
        'pickup_enabled' => 'boolean',
        'shipping_enabled' => 'boolean',
        'allows_hand_delivery' => 'boolean',
        'allows_colissimo' => 'boolean',
        'allows_offers' => 'boolean',
        'allows_exchange' => 'boolean',
        'requires_online_payment' => 'boolean',
        'photoless_hidden_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * L'article est-il RÉELLEMENT payable par carte maintenant ? Il ne suffit
     * pas que l'annonce soit marquée « paiement en ligne » : le vendeur doit
     * avoir un compte Stripe opérationnel (encaissements ET versements activés).
     * Un simple stripe_account_id ne suffit pas (onboarding souvent incomplet).
     */
    public function isOnlinePayable(): bool
    {
        return (bool) $this->requires_online_payment
            && $this->user
            && $this->user->stripe_account_id
            && $this->user->stripe_charges_enabled
            && $this->user->stripe_payouts_enabled;
    }

    /** Filtre : uniquement les annonces réellement payables par carte. */
    public function scopeOnlinePayable($query)
    {
        return $query->where('requires_online_payment', true)
            ->whereHas('user', fn ($q) => $q->whereNotNull('stripe_account_id')
                ->where('stripe_charges_enabled', true)
                ->where('stripe_payouts_enabled', true));
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

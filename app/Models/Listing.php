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
        if (! $this->requires_online_payment) {
            return false;
        }

        // Point 19 — KYC différé : le paiement CB est un encaissement sur le
        // compte plateforme (separate charges & transfers). Le vendeur n'a
        // besoin d'un compte opérationnel qu'au virement, pas à la vente : une
        // annonce en paiement en ligne est donc payable même sans KYC complet.
        // Les fonds sont sécurisés sur la plateforme jusqu'à la remise, et
        // l'acheteur est remboursé si le vendeur ne finalise jamais.
        if (config('features.defer_kyc')) {
            return true;
        }

        return $this->user
            && $this->user->stripe_account_id
            && $this->user->stripe_charges_enabled
            && $this->user->stripe_payouts_enabled;
    }

    /** Filtre : uniquement les annonces réellement payables par carte. */
    public function scopeOnlinePayable($query)
    {
        // Point 19 — cf. isOnlinePayable() : en KYC différé, l'intention de
        // paiement en ligne suffit (encaissement plateforme).
        if (config('features.defer_kyc')) {
            return $query->where('requires_online_payment', true);
        }

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

    /**
     * Point 18 — acheteurs plausibles pour une vente en espèces : les personnes
     * qui ont mis l'annonce en favori OU qui ont échangé des messages à son
     * sujet, hors vendeur. Sert à capter QUI a acheté sur une vente cash.
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\User>
     */
    public function cashBuyerCandidates()
    {
        $favIds = $this->favoritedBy()->get(['users.id'])->pluck('id');

        $msgIds = Message::query()
            ->where('listing_id', $this->id)
            ->get(['sender_id', 'receiver_id'])
            ->flatMap(fn ($m) => [$m->sender_id, $m->receiver_id]);

        $ids = $favIds->concat($msgIds)
            ->filter()
            ->reject(fn ($id) => (int) $id === (int) $this->user_id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $ids->all())
            ->orderBy('name')
            ->get();
    }
}

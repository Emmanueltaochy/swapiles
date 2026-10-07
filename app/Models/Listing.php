<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Listing extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sharetribe_id',
        'submission_token',
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
        'category_avant',
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
        'category_avant' => 'array',
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

    /**
     * Fiche de champs du rayon de l'annonce (voir Categories::fiche) : dit
     * comment s'appelle la colonne « taille » ici (pointure, dimensions,
     * capacité…) et comment se lit l'état.
     */
    public function ficheChamps(): array
    {
        return \App\Support\Categories::fiche($this->category_level1, $this->category_level2);
    }

    /** Libellé de la caractéristique « taille » de ce rayon. */
    public function libelleTaille(): string
    {
        return $this->ficheChamps()['taille']['label'] ?? 'Taille';
    }

    /** Libellé de la « marque » de ce rayon (« Auteur » pour un livre). */
    public function libelleMarque(): string
    {
        return $this->ficheChamps()['marque']['label'] ?? 'Marque';
    }

    /**
     * La taille telle qu'on l'affiche. En majuscules pour un vêtement (« M »,
     * « XL »), telle quelle ailleurs : « 128 GO » ou « 60 X 180 CM » étaient
     * faux.
     */
    public function tailleAffichee(): ?string
    {
        if (blank($this->taille)) {
            return null;
        }

        return $this->ficheChamps()['etat'] === 'textile'
            ? mb_strtoupper($this->taille)
            : $this->taille;
    }

    /** L'état tel qu'on l'affiche, dans les mots du rayon. */
    public function etatAffiche(): ?string
    {
        return \App\Support\Etat::libelle($this->etat, $this->category_level1 ?: null, $this->category_level2);
    }

    /**
     * Ligne de résumé des cartes : « M · Bon état · Zara ». Les morceaux
     * absents sont simplement omis (avant, une annonce sans taille
     * commençait par « · »).
     */
    public function resumeCaracteristiques(): string
    {
        return implode(' · ', array_filter([
            $this->tailleAffichee(),
            $this->etatAffiche(),
            $this->marque,
        ]));
    }

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

    /** Surcharge « points relais acceptés » propre à cette annonce. */
    public function relayPoints()
    {
        return $this->belongsToMany(RelayPoint::class, 'listing_relay_point');
    }

    /**
     * Points relais EXPLICITEMENT retenus pour cette annonce (surcharge annonce
     * si renseignée, sinon défaut du vendeur). N'inclut PAS le repli « tous les
     * relais de l'île » : renvoie une collection vide si rien n'a été coché.
     * Utile pour l'admin (voir les produits rattachés à un point de vente).
     */
    public function selectedRelayPoints()
    {
        $territoire = $this->territoire;

        $filter = fn ($points) => $points
            ->where('is_active', true)
            ->where('territoire', $territoire)
            ->values();

        $listingChoice = $filter($this->relayPoints()->get());
        if ($listingChoice->isNotEmpty()) {
            return $listingChoice;
        }

        return $filter(optional($this->user)->acceptedRelayPoints()->get() ?? collect());
    }

    /**
     * Périmètre de points relais réellement proposé à l'acheteur, par ordre de
     * priorité :
     *   1. la surcharge de l'annonce (si le vendeur en a coché) ;
     *   2. sinon les relais par défaut du vendeur ;
     *   3. sinon tous les relais actifs de l'île de l'annonce.
     * Toujours filtré aux relais ACTIFS du territoire de l'annonce.
     */
    public function effectiveRelayPoints()
    {
        if (! config('features.relay_points')) {
            return collect();
        }

        $territoire = $this->territoire;

        $filter = fn ($points) => $points
            ->where('is_active', true)
            ->where('territoire', $territoire)
            ->values();

        $listingChoice = $filter($this->relayPoints()->get());
        if ($listingChoice->isNotEmpty()) {
            return $listingChoice;
        }

        $sellerChoice = $filter(optional($this->user)->acceptedRelayPoints()->get() ?? collect());
        if ($sellerChoice->isNotEmpty()) {
            return $sellerChoice;
        }

        return RelayPoint::activeForTerritoire($territoire);
    }
}

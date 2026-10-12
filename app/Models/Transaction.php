<?php

namespace App\Models;

use App\Jobs\SendSellerPaymentReceivedEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharetribe_id',
        'listing_id',
        'listing_offer_id',
        'seller_id',
        'buyer_id',
        'amount',
        'commission',
        'platform_commission',
        'currency',
        'payment_method',
        'seller_amount',
        'shipping_fee',
        'buyer_protection_fee',
        'delivery_method',
        'pickup_type',
        'pickup_country',
        'pickup_city',
        'pickup_postal_code',
        'pickup_address',
        'pickup_name',
        'pickup_id',
        'colissimo_delivery_type',
        'buyer_full_name',
        'buyer_phone',
        'shipping_address_line1',
        'shipping_address_line2',
        'shipping_postal_code',
        'shipping_city',
        'shipping_country',
        'hand_delivery_location',
        'relay_point_id',
        'relay_fee',
        'relay_merchant_fee',
        'relay_status',
        'relay_pickup_code',
        'relay_deposited_at',
        'relay_collected_at',
        'status',
        'shipping_status',
        'wallet_status',
        'stripe_payment_intent_id',
        'stripe_transfer_id',
        'carrier',
        'tracking_number',
        'tracking_url',
        'completed_at',
        'shipped_at',
        'received_at',
        'delivered_at',
        'released_at',
        'transfer_started_at',
        'transferred_at',
        'estimated_payout_date',
        'auto_review_flagged_at',
        'acheteur_confirme_at',
        'acheteur_conteste_at',
    ];

    /**
     * Ventes DÉCLARÉES par le vendeur (bouton « Paiement espèces reçu »,
     * « Échange effectué », « Don remis ») : aucun argent n'est passé par
     * Swap'Îles, on n'a que la parole du vendeur.
     */
    public const MODES_DECLARES = ['especes', 'echange', 'don'];

    /** Niveau de preuve d'une vente, du plus sûr au moins sûr. */
    public const FIABILITES = [
        'en_ligne' => ['Payée en ligne', 'success', 'Paiement par carte passé par Swap\'Îles : vente certaine.'],
        'confirmee' => ['Confirmée par l\'acheteur', 'success', 'Le vendeur a déclaré la vente et l\'acheteur a confirmé.'],
        'declaree_membre' => ['Déclarée, acheteur membre', 'warning', 'Le vendeur a désigné un membre comme acheteur ; celui-ci n\'a pas encore confirmé.'],
        'declaree_inconnu' => ['Déclarée, acheteur inconnu', 'gray', 'Seulement la parole du vendeur : acheteur hors Swap\'Îles ou non indiqué. Peut être une annonce simplement retirée.'],
        'contestee' => ['Contestée par l\'acheteur', 'danger', 'Le membre désigné dit ne pas avoir acheté cet article.'],
        'echange' => ['Échange déclaré', 'gray', 'Échange déclaré par le vendeur, sans argent.'],
        'don' => ['Don déclaré', 'gray', 'Don déclaré par le vendeur, sans argent.'],
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'released_at' => 'datetime',
        'auto_review_flagged_at' => 'datetime',
        'relay_deposited_at' => 'datetime',
        'relay_collected_at' => 'datetime',
        'acheteur_confirme_at' => 'datetime',
        'acheteur_conteste_at' => 'datetime',
    ];

    /** Ventes dont le paiement est passé par Swap'Îles (pas une simple déclaration). */
    public function scopeEnLigne($query)
    {
        return $query->where(fn ($q) => $q->whereNull('payment_method')->orWhereNotIn('payment_method', self::MODES_DECLARES));
    }

    /** Ventes seulement déclarées par le vendeur. */
    public function scopeDeclarees($query)
    {
        return $query->whereIn('payment_method', self::MODES_DECLARES);
    }

    public function estDeclaree(): bool
    {
        return in_array($this->payment_method, self::MODES_DECLARES, true);
    }

    /** Clé de self::FIABILITES. */
    public function fiabilite(): string
    {
        return match (true) {
            ! $this->estDeclaree() => 'en_ligne',
            $this->payment_method === 'echange' => 'echange',
            $this->payment_method === 'don' => 'don',
            $this->acheteur_conteste_at !== null => 'contestee',
            $this->acheteur_confirme_at !== null => 'confirmee',
            $this->buyer_id !== null => 'declaree_membre',
            default => 'declaree_inconnu',
        };
    }

    public function fiabiliteLibelle(): string
    {
        return self::FIABILITES[$this->fiabilite()][0];
    }

    /** L'acheteur désigné peut encore répondre « c'est moi » / « ce n'est pas moi ». */
    public function attendReponseAcheteur(): bool
    {
        return $this->payment_method === 'especes'
            && $this->buyer_id !== null
            && $this->acheteur_confirme_at === null
            && $this->acheteur_conteste_at === null;
    }

    public function relayPoint()
    {
        return $this->belongsTo(RelayPoint::class);
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function getAmountInEurosAttribute()
    {
        return $this->amount;
    }

    public function getCommissionInEurosAttribute()
    {
        return $this->commission;
    }

    /** Vente sécurisée (argent passé par Stripe) — seule à créditer le wallet. */
    public function getIsSecuredAttribute(): bool
    {
        return \App\Support\SellerWallet::isSecured($this);
    }

    /** Mode de paiement normalisé pour l'affichage (badge du journal). */
    public function getPaymentModeAttribute(): string
    {
        return \App\Support\SellerWallet::mode($this);
    }

    public function getPaymentModeLabelAttribute(): string
    {
        return \App\Support\SellerWallet::modeLabel($this->payment_mode);
    }

    /**
     * L'article a-t-il été expédié ? On se fie au numéro de suivi (décision
     * produit : « expédié » = un suivi transporteur existe).
     */
    public function hasBeenShipped(): bool
    {
        return filled($this->tracking_number);
    }

    /**
     * Montant du remboursement acheteur, en euros (décision produit) :
     *   - jamais expédié (pas de suivi) -> remboursement INTÉGRAL, port compris ;
     *   - déjà expédié -> prix + protection uniquement (le port est consommé).
     */
    public function refundAmountEuros(): float
    {
        if (!$this->hasBeenShipped()) {
            return max(0.0, (float) $this->amount);
        }

        return max(0.0, (float) $this->amount - (float) $this->shipping_fee);
    }

    protected static function booted(): void
    {
        static::created(function (Transaction $transaction) {
            $transaction->sendSellerPaidEmailIfNeeded();
        });

        static::updated(function (Transaction $transaction) {
            $transaction->sendSellerPaidEmailIfNeeded();
        });
    }

    public function sendSellerPaidEmailIfNeeded(): void
    {
        if ($this->status !== 'paid') {
            return;
        }

        if (! empty($this->seller_paid_email_sent_at)) {
            return;
        }

        $this->loadMissing(['seller', 'buyer', 'listing']);

        if (! $this->seller || ! $this->seller->email) {
            return;
        }

        SendSellerPaymentReceivedEmail::dispatch($this->id);

        $this->forceFill([
            'seller_paid_email_sent_at' => now(),
        ])->saveQuietly();
    }

}

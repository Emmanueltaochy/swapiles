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
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'released_at' => 'datetime',
        'auto_review_flagged_at' => 'datetime',
    ];

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

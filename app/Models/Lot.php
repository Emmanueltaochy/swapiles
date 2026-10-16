<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Commande groupée : plusieurs articles d'un même dressing, un seul paiement. */
class Lot extends Model
{
    protected $fillable = ['buyer_id', 'seller_id', 'nb_articles', 'remise_pct', 'remise_montant'];

    protected $casts = ['remise_montant' => 'decimal:2'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderBy('id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}

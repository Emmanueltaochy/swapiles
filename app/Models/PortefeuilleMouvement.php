<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ligne du portefeuille. Les crédits (parrainage, restitution) sont des
 * « lots » avec un reste et une date d'expiration ; les débits (utilisation,
 * expiration, annulation) pointent vers le lot entamé.
 */
class PortefeuilleMouvement extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expire_at' => 'datetime',
        'restitue_at' => 'datetime',
        'rappel_envoye_at' => 'datetime',
    ];

    public const LOTS = ['credit_parrainage', 'restitution'];

    public const LIBELLES = [
        'credit_parrainage' => 'Crédit parrainage',
        'restitution' => 'Crédit rendu (commande annulée)',
        'utilisation' => 'Utilisé pour un achat',
        'expiration' => 'Crédit expiré',
        'annulation' => 'Crédit annulé',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function parrainage(): BelongsTo
    {
        return $this->belongsTo(Parrainage::class);
    }
}

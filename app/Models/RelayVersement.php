<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Relevé mensuel d'un point relais (commissions du mois) et son versement. */
class RelayVersement extends Model
{
    protected $fillable = ['relay_point_id', 'mois', 'colis', 'montant', 'numero', 'verse_at', 'reference'];

    protected $casts = [
        'mois' => 'date',
        'montant' => 'decimal:2',
        'verse_at' => 'datetime',
    ];

    public function relayPoint()
    {
        return $this->belongsTo(RelayPoint::class);
    }
}

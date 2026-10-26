<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Relevé mensuel d'un point relais (commissions du mois) et son versement. */
class RelayVersement extends Model
{
    protected $fillable = ['relay_point_id', 'mois', 'colis', 'montant', 'retenue', 'retenue_motif', 'numero', 'verse_at', 'reference'];

    protected $casts = [
        'mois' => 'date',
        'montant' => 'decimal:2',
        'retenue' => 'decimal:2',
        'verse_at' => 'datetime',
    ];

    /** Ce qui part vraiment par virement : commissions du mois moins l'éventuelle retenue. */
    public function net(): float
    {
        return round(max(0, (float) $this->montant - (float) $this->retenue), 2);
    }

    public function relayPoint()
    {
        return $this->belongsTo(RelayPoint::class);
    }
}

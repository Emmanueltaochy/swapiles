<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Organisme public (mairie, CCAS, intercommunalité…) contacté pour le réemploi / RSE. */
class ProspectRse extends Model
{
    protected $table = 'prospects_rse';

    public const STATUTS = [
        'nouveau' => 'À contacter',
        'contacte' => 'Mail envoyé',
        'relance' => 'Relancé',
        'repondu' => 'A répondu',
        'interesse' => 'Intéressé',
        'refuse' => 'Pas intéressé',
        'desinscrit' => 'Ne plus écrire',
    ];

    protected $attributes = ['statut' => 'nouveau', 'nb_envois' => 0, 'nb_clics' => 0];

    protected $fillable = [
        'source_id', 'nom', 'type', 'territoire', 'ville', 'code_postal', 'email', 'site_web', 'telephone',
        'statut', 'contacte_at', 'relance_at', 'repondu_at', 'nb_envois', 'jeton', 'clique_at', 'nb_clics', 'notes',
    ];

    protected $casts = [
        'contacte_at' => 'datetime',
        'relance_at' => 'datetime',
        'repondu_at' => 'datetime',
        'clique_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ProspectRse $p) {
            $p->jeton ??= Str::random(40);
        });
    }

    public function scopeContactable($query)
    {
        return $query->whereNotNull('email')->where('email', '!=', '')->where('statut', 'nouveau');
    }
}

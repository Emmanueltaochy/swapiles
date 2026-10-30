<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Commerce repéré qui pourrait devenir point relais (prospection). */
class RelaisProspect extends Model
{
    public const STATUTS = [
        'nouveau' => 'À contacter',
        'contacte' => 'Mail envoyé',
        'interesse' => 'Intéressé',
        'refuse' => 'Pas intéressé',
        'relais' => 'Devenu point relais',
        'desinscrit' => 'Ne plus écrire',
    ];

    protected $fillable = [
        'source_id', 'nom', 'type', 'adresse', 'code_postal', 'ville', 'territoire', 'latitude', 'longitude',
        'telephone', 'email', 'site_web', 'horaires', 'email_cherche_at', 'statut', 'contacte_at', 'nb_envois', 'jeton', 'notes', 'demande',
    ];

    protected $casts = [
        'email_cherche_at' => 'datetime',
        'contacte_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (RelaisProspect $p) {
            $p->jeton ??= Str::random(40);
        });
    }

    public function scopeContactable($query)
    {
        return $query->whereNotNull('email')->where('email', '!=', '')
            ->whereIn('statut', ['nouveau']);
    }
}

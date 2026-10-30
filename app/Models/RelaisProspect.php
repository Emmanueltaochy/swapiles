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
        'telephone', 'email', 'site_web', 'horaires', 'email_cherche_at', 'statut', 'contacte_at', 'nb_envois', 'jeton', 'notes', 'demande', 'clique_at', 'nb_clics', 'formulaire_at', 'parcours',
    ];

    protected $casts = [
        'email_cherche_at' => 'datetime',
        'contacte_at' => 'datetime',
        'clique_at' => 'datetime',
        'formulaire_at' => 'datetime',
        'parcours' => 'array',
    ];

    /** Étapes du parcours après le clic sur le mail, des plus anciennes aux plus récentes. */
    public const PARCOURS_MAX = 40;

    protected static function booted(): void
    {
        static::creating(function (RelaisProspect $p) {
            $p->jeton ??= Str::random(40);
        });
    }

    /** Ajoute une étape au parcours (une étape identique à la précédente n'est pas répétée). */
    public function noterEtape(string $etape): void
    {
        $parcours = $this->parcours ?? [];
        $derniere = end($parcours);
        if ($derniere && ($derniere['e'] ?? null) === $etape) {
            return;
        }
        $parcours[] = ['t' => now()->format('d/m H:i'), 'e' => $etape];
        $this->forceFill(['parcours' => array_slice($parcours, -self::PARCOURS_MAX)])->save();
    }

    public function scopeContactable($query)
    {
        return $query->whereNotNull('email')->where('email', '!=', '')
            ->whereIn('statut', ['nouveau']);
    }
}

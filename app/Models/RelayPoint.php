<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Point relais partenaire (commerçant). Garde le colis d'une vente CB pour
 * l'acheteur. Pilote : La Réunion.
 */
class RelayPoint extends Model
{
    protected $fillable = [
        'name',
        'territoire',
        'address',
        'postal_code',
        'city',
        'contact_name',
        'contact_phone',
        'opening_hours',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Points relais actifs d'un territoire donné (pour le tunnel d'achat). */
    public static function activeForTerritoire(?string $territoire)
    {
        return static::query()
            ->active()
            ->where('territoire', $territoire)
            ->orderBy('city')
            ->orderBy('name')
            ->get();
    }

    /** Adresse compacte sur une ligne (affichage acheteur / vendeur). */
    public function fullAddress(): string
    {
        return collect([$this->address, trim(($this->postal_code ?? '').' '.($this->city ?? ''))])
            ->filter()
            ->implode(', ');
    }
}

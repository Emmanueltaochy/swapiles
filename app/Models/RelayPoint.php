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
        'manager_user_id',
        'territoire',
        'address',
        'postal_code',
        'city',
        'latitude',
        'longitude',
        'contact_name',
        'contact_phone',
        'opening_hours',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Coordonnées [lat, lng] du point relais pour la carte : coordonnées
     * saisies si présentes, sinon centre de la commune (DomTomGeo). Null si
     * on ne sait pas placer le pin.
     */
    public function coordinates(): ?array
    {
        if ($this->latitude !== null && $this->longitude !== null) {
            return [(float) $this->latitude, (float) $this->longitude];
        }

        return \App\Support\DomTomGeo::coords($this->territoire, $this->city, $this->postal_code);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /** Le commerçant qui gère ce point relais (accès à l'espace relais). */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /**
     * Solde gagné par le commerçant sur ce point : 1 € (relay_merchant_fee) par
     * colis effectivement REMIS à l'acheteur (relay_status = collected).
     */
    public function earnedBalance(): float
    {
        return (float) $this->transactions()
            ->where('relay_status', 'collected')
            ->sum('relay_merchant_fee');
    }

    /** Nombre de colis remis (comptabilisés dans le solde). */
    public function collectedCount(): int
    {
        return (int) $this->transactions()
            ->where('relay_status', 'collected')
            ->count();
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

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
        'partenaire_enseigne',
        'partenaire_logo',
        'partenaire_depuis',
        'raison_sociale',
        'siret',
        'assujetti_tva',
    ];

    /** Colis qui rapportent la commission : remis à l'acheteur, ou rendu au vendeur après le délai de garde. */
    public const STATUTS_REMUNERES = ['collected', 'returned'];

    protected $casts = [
        'is_active' => 'boolean',
        'partenaire_depuis' => 'date',
        'charte_acceptee_at' => 'datetime',
        'assujetti_tva' => 'boolean',
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
     * colis effectivement REMIS (à l'acheteur, ou rendu au vendeur après le délai de garde).
     */
    public function earnedBalance(): float
    {
        return (float) $this->transactions()
            ->whereIn('relay_status', self::STATUTS_REMUNERES)
            ->sum('relay_merchant_fee');
    }

    /** Nombre de colis remis (comptabilisés dans le solde). */
    public function collectedCount(): int
    {
        return (int) $this->transactions()
            ->whereIn('relay_status', self::STATUTS_REMUNERES)
            ->count();
    }

    public function versements()
    {
        return $this->hasMany(RelayVersement::class);
    }

    /** Le commerçant a-t-il accepté la version en vigueur de la charte ? */
    public function charteAJour(): bool
    {
        return $this->charte_acceptee_at !== null
            && $this->charte_version === (string) config('relais.charte_version');
    }

    public function accepterCharte(User $gerant): void
    {
        $this->forceFill([
            'charte_version' => (string) config('relais.charte_version'),
            'charte_acceptee_at' => now(),
            'charte_acceptee_par' => $gerant->id,
        ])->save();
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

    /** Tenu par une enseigne partenaire (badge et logo dans l'appli). */
    public function estPartenaire(): bool
    {
        return filled($this->partenaire_enseigne);
    }

    public function logoUrl(): ?string
    {
        if (blank($this->partenaire_logo)) {
            return null;
        }

        return str_starts_with($this->partenaire_logo, 'http') || str_starts_with($this->partenaire_logo, '/')
            ? $this->partenaire_logo
            : \Illuminate\Support\Facades\Storage::disk('public')->url($this->partenaire_logo);
    }

    /** Jeton du lien de rapport à envoyer au partenaire (créé à la demande). */
    public function jetonRapport(): string
    {
        if (blank($this->rapport_jeton)) {
            $this->forceFill(['rapport_jeton' => \Illuminate\Support\Str::random(40)])->save();
        }

        return $this->rapport_jeton;
    }

    public function lienRapport(): string
    {
        return route('partenaires.rapport', $this->jetonRapport());
    }
}

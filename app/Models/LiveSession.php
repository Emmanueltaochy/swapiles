<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Un live : un vendeur présente ses articles en direct. */
class LiveSession extends Model
{
    public const STATUTS = [
        'programme' => 'Programmé',
        'en_direct' => 'En direct',
        'termine' => 'Terminé',
    ];

    protected $fillable = [
        'user_id', 'titre', 'description', 'statut', 'debut_prevu_at', 'demarre_at',
        'termine_at', 'article_epingle_id', 'spectateurs_max', 'coeurs', 'coupe_raison',
    ];

    protected $casts = [
        'debut_prevu_at' => 'datetime',
        'demarre_at' => 'datetime',
        'termine_at' => 'datetime',
    ];

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'live_session_listing')->withPivot('ordre')->orderByPivot('ordre');
    }

    public function articleEpingle(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'article_epingle_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(LiveMessage::class);
    }

    public function enDirect(): bool
    {
        return $this->statut === 'en_direct';
    }

    public function estVendeur(?User $user): bool
    {
        return $user !== null && (int) $user->id === (int) $this->user_id;
    }
}

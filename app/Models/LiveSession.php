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
        'rappel_envoye_at', 'enregistrement_id', 'replay_chemin', 'replay_publie_at',
    ];

    protected $casts = [
        'debut_prevu_at' => 'datetime',
        'demarre_at' => 'datetime',
        'termine_at' => 'datetime',
        'rappel_envoye_at' => 'datetime',
        'replay_publie_at' => 'datetime',
    ];

    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'live_session_listing')->withPivot('ordre', 'visible')->orderByPivot('ordre');
    }

    /** Articles que le vendeur a déjà dévoilés aux spectateurs. */
    public function articlesVisibles(): BelongsToMany
    {
        return $this->articles()->wherePivot('visible', true);
    }

    /** Membres qui ont demandé à être prévenus du début du live. */
    public function abonnes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'live_rappels')->withTimestamps();
    }

    /** Lien public de la vidéo enregistrée (null si pas d'enregistrement). */
    public function replayUrl(): ?string
    {
        $base = rtrim((string) config('live.replay.url_publique'), '/');

        return $this->replay_chemin && $base !== '' ? $base . '/' . $this->replay_chemin : null;
    }

    public function replayPublie(): bool
    {
        return $this->replay_publie_at !== null && $this->replayUrl() !== null;
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

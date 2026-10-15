<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Une notification push : à qui, quoi, son sort, et les clics. */
class PushEnvoi extends Model
{
    protected $table = 'push_envois';

    protected $guarded = [];

    protected $casts = [
        'prevu_at' => 'datetime',
        'envoye_at' => 'datetime',
        'premier_clic_at' => 'datetime',
    ];

    public const STATUTS = [
        'en_file' => 'En cours d’envoi',
        'differe' => 'Programmée (heures de silence)',
        'envoye' => 'Partie',
        'echec' => 'Échec',
        'sans_appareil' => 'Aucun appareil',
        'ignore' => 'Non envoyée',
        'coupe' => 'Coupée par le membre',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clicsDetail(): HasMany
    {
        return $this->hasMany(PushClic::class)->latest('created_at');
    }

    public function estDiffusion(): bool
    {
        return $this->user_id === null && $this->origine === 'manuel';
    }

    /** Part des appareils atteints qui ont ouvert la notification (en %). */
    public function tauxClic(): ?float
    {
        $base = $this->estDiffusion() ? $this->appareils_ok : ($this->statut === 'envoye' ? 1 : 0);

        return $base > 0 ? round(min(100, $this->clics / $base * 100), 1) : null;
    }
}

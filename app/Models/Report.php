<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Signalement d'un contenu par un membre : annonce, membre ou message.
 * Alimente le back-office (Communauté > Signalements) pour modération.
 */
class Report extends Model
{
    protected $fillable = [
        'reporter_id', 'reportable_type', 'reportable_id',
        'reason', 'details', 'status', 'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];

    /** Motifs proposés à l'utilisateur (clé stockée => libellé affiché). */
    public const REASONS = [
        'contrefacon' => 'Contrefaçon / faux article',
        'interdit' => 'Article interdit ou illégal',
        'arnaque' => 'Arnaque ou comportement suspect',
        'offensant' => 'Contenu offensant ou choquant',
        'harcelement' => 'Harcèlement ou insultes',
        'spam' => 'Spam ou publicité',
        'autre' => 'Autre',
    ];

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /** Libellé lisible du motif. */
    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}

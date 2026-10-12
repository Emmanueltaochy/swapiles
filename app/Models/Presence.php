<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un visiteur humain en direct (voir VisiteursEnDirect). */
class Presence extends Model
{
    protected $fillable = [
        'cle',
        'ip_hash',
        'user_id',
        'territoire',
        'path',
        'appareil',
        'appli',
        'source',
        'pages',
        'debut_at',
        'last_seen_at',
    ];

    protected $casts = [
        'appli' => 'boolean',
        'debut_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jeton d'un appareil (installation de l'app) pour les notifications push FCM.
 */
class DeviceToken extends Model
{
    protected $fillable = [
        'user_id', 'token', 'platform', 'last_seen_at',
        'last_result', 'last_error', 'last_sent_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_sent_at' => 'datetime',
    ];

    /**
     * Appareils encore actifs : l'app renvoie son jeton à chaque lancement,
     * donc un jeton revu récemment correspond à une installation vivante.
     */
    public function scopeActifs($query, ?int $jours = null)
    {
        $jours ??= (int) config('push.active_days', 30);

        return $query->where('last_seen_at', '>=', now()->subDays($jours));
    }

    /** Jetons plus revus depuis longtemps : réinstallations, désinstallations. */
    public function scopeObsoletes($query, ?int $jours = null)
    {
        $jours ??= (int) config('push.active_days', 30);

        return $query->where(function ($q) use ($jours) {
            $q->whereNull('last_seen_at')
                ->orWhere('last_seen_at', '<', now()->subDays($jours));
        });
    }

    /** Version courte du jeton, pour l'afficher sans tout dévoiler. */
    public function tokenApercu(): string
    {
        $t = (string) $this->token;

        return mb_strlen($t) > 16 ? mb_substr($t, 0, 8) . '…' . mb_substr($t, -6) : $t;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Empreinte technique d'une inscription / connexion (IP réelle, user-agent,
 * empreinte d'appareil). Base de la détection multicompte.
 */
class UserSession extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'ip', 'user_agent', 'device_fingerprint', 'event_type', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Enregistre une session (best-effort : ne casse jamais l'auth).
     *
     * @param  string  $eventType  registration | login
     */
    public static function record(?int $userId, Request $request, string $eventType): void
    {
        try {
            static::create([
                'user_id' => $userId,
                'ip' => static::clientIp($request),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000) ?: null,
                'device_fingerprint' => static::deviceFingerprint($request),
                'event_type' => $eventType,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * IP client RÉELLE derrière le proxy/CDN.
     *  1. CF-Connecting-IP (Cloudflare l'écrase → non falsifiable derrière CF) ;
     *  2. sinon l'IP calculée par Laravel après TrustProxies (1er hop X-Forwarded-For) ;
     *  3. sinon l'IP distante brute.
     */
    public static function clientIp(Request $request): ?string
    {
        $cf = $request->headers->get('CF-Connecting-IP');
        if ($cf && filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }

        $ip = $request->ip();

        return $ip ?: null;
    }

    /** Empreinte d'appareil transmise par le formulaire (hash hexadécimal). */
    public static function deviceFingerprint(Request $request): ?string
    {
        $fp = trim((string) $request->input('device_fingerprint', ''));

        if ($fp === '' || ! preg_match('/^[a-f0-9]{16,128}$/i', $fp)) {
            return null;
        }

        return mb_strtolower($fp);
    }

    /**
     * Autres comptes ayant partagé la même IP OU la même empreinte que ce user.
     * Cœur de la détection multicompte.
     *
     * @return \Illuminate\Support\Collection<int, int>  user_ids liés (hors soi-même)
     */
    public static function linkedUserIds(int $userId): \Illuminate\Support\Collection
    {
        $own = static::query()
            ->where('user_id', $userId)
            ->get(['ip', 'device_fingerprint']);

        $ips = $own->pluck('ip')->filter()->unique()->values();
        $fps = $own->pluck('device_fingerprint')->filter()->unique()->values();

        if ($ips->isEmpty() && $fps->isEmpty()) {
            return collect();
        }

        return static::query()
            ->where('user_id', '!=', $userId)
            ->whereNotNull('user_id')
            ->where(function ($q) use ($ips, $fps) {
                if ($ips->isNotEmpty()) {
                    $q->orWhereIn('ip', $ips->all());
                }
                if ($fps->isNotEmpty()) {
                    $q->orWhereIn('device_fingerprint', $fps->all());
                }
            })
            ->distinct()
            ->pluck('user_id')
            ->unique()
            ->values();
    }
}

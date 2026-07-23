<?php

namespace App\Http\Middleware;

use App\Models\LiveVisit;
use Closure;
use Illuminate\Http\Request;

class TrackLiveVisit
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            if (!$request->isMethod('GET')) {
                return $response;
            }

            if ($request->is('admin*') || $request->is('livewire*') || $request->is('build*') || $request->is('storage*') || $request->is('n/*')) {
                return $response;
            }

            // On ignore les robots pour ne pas fausser le compteur « en direct ».
            if (\App\Support\BotDetector::isBot($request->userAgent())) {
                return $response;
            }

            $territoire = $request->cookie('swapiles_territoire') ?: 'La Réunion';

            $coords = [
                'La Réunion' => [-21.1151, 55.5364],
                'Martinique' => [14.6415, -61.0242],
                'Guadeloupe' => [16.2650, -61.5510],
                'Guyane' => [3.9339, -53.1258],
                'Mayotte' => [-12.8275, 45.1662],
            ];

            [$lat, $lng] = $coords[$territoire] ?? $coords['La Réunion'];

            $ua = strtolower((string) $request->userAgent());
            $device = str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android') ? 'Mobile' : 'Desktop';

            LiveVisit::updateOrCreate(
                ['ip_hash' => hash('sha256', $request->ip() . config('app.key'))],
                [
                    'user_id' => optional($request->user())->id,
                    'territoire' => $territoire,
                    'url' => mb_substr($request->fullUrl(), 0, 1000),
                    'path' => '/' . ltrim($request->path(), '/'),
                    'device' => $device,
                    'lat' => $lat + (mt_rand(-100, 100) / 10000),
                    'lng' => $lng + (mt_rand(-100, 100) / 10000),
                    'last_seen_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }
}

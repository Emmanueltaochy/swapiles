<?php

namespace App\Http\Middleware;

use App\Models\AnalyticsEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class TrackAnalyticsPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (!$request->isMethod('GET')) {
                return;
            }

            if ($request->ajax() || $request->expectsJson()) {
                return;
            }

            if (!Schema::hasTable('analytics_events')) {
                return;
            }

            $path = trim($request->path(), '/');
            $path = $path === '' ? '/' : '/' . $path;

            if (
                str_starts_with($path, '/admin') ||
                str_starts_with($path, '/api') ||
                str_starts_with($path, '/livewire') ||
                str_starts_with($path, '/build') ||
                str_starts_with($path, '/storage') ||
                str_starts_with($path, '/_debugbar') ||
                str_contains($path, 'favicon')
            ) {
                return;
            }

            $routeName = optional($request->route())->getName();

            if ($routeName && str_starts_with($routeName, 'filament.')) {
                return;
            }

            $userAgent = (string) $request->userAgent();

            // On n'enregistre pas les robots/crawlers pour ne pas gonfler les stats.
            if (\App\Support\BotDetector::isBot($userAgent)) {
                return;
            }

            AnalyticsEvent::create([
                'user_id' => optional($request->user())->id,
                'session_id' => optional($request->session())->getId(),
                'ip_address' => $request->ip(),

                'method' => $request->method(),
                'path' => mb_substr($path, 0, 500),
                'url' => mb_substr($request->fullUrl(), 0, 2000),
                'referer' => mb_substr((string) $request->headers->get('referer'), 0, 2000),

                'route_name' => $routeName ? mb_substr($routeName, 0, 255) : null,
                'page_name' => $this->pageName($path, $routeName),

                'device' => $this->device($userAgent),
                'browser' => $this->browser($userAgent),
                'user_agent' => mb_substr($userAgent, 0, 2000),

                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Ne jamais casser le site pour une donnée analytics.
            report($e);
        }
    }

    private function pageName(string $path, ?string $routeName): string
    {
        if ($path === '/') {
            return 'Accueil';
        }

        if (str_contains($path, '/annonce/')) {
            return 'Page annonce';
        }

        if (str_contains($path, '/produits') || str_contains($path, '/recherche')) {
            return 'Recherche produits';
        }

        if (str_contains($path, '/checkout')) {
            return 'Checkout';
        }

        if (str_contains($path, '/messages')) {
            return 'Messages';
        }

        if (str_contains($path, '/mon-compte') || str_contains($path, '/account')) {
            return 'Mon compte';
        }

        if (str_contains($path, '/wallet')) {
            return 'Wallet';
        }

        if (str_contains($path, '/deposer')) {
            return 'Déposer une annonce';
        }

        return $routeName ?: $path;
    }

    private function device(string $userAgent): string
    {
        $ua = mb_strtolower($userAgent);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'tablette';
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        return 'desktop';
    }

    private function browser(string $userAgent): string
    {
        $ua = mb_strtolower($userAgent);

        if (str_contains($ua, 'edg/')) {
            return 'Edge';
        }

        if (str_contains($ua, 'chrome') || str_contains($ua, 'crios')) {
            return 'Chrome';
        }

        if (str_contains($ua, 'safari')) {
            return 'Safari';
        }

        if (str_contains($ua, 'firefox')) {
            return 'Firefox';
        }

        return 'Autre';
    }
}

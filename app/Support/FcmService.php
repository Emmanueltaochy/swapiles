<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de notifications push via Firebase Cloud Messaging (API HTTP v1).
 *
 * L'authentification se fait avec le compte de service Firebase (fichier JSON) :
 * on signe un JWT (RS256) qu'on échange contre un jeton d'accès OAuth2, mis en
 * cache ~55 min. Aucune librairie externe requise (openssl + client HTTP Laravel).
 */
class FcmService
{
    /** Le push est-il configuré (fichier de compte de service présent et valide) ? */
    public static function configured(): bool
    {
        return filled((new self)->projectId());
    }

    /** ID du projet Firebase : config explicite, sinon lu dans le fichier de compte de service. */
    private function projectId(): ?string
    {
        $configured = config('push.fcm.project_id');
        if (filled($configured)) {
            return $configured;
        }

        $path = config('push.fcm.credentials_path');
        if ($path && is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);

            return $data['project_id'] ?? null;
        }

        return null;
    }

    /**
     * Envoie une notification à un jeton d'appareil.
     *
     * @return string 'ok' (envoyé), 'invalid' (jeton mort à supprimer),
     *                'skipped' (non configuré) ou 'error' (échec temporaire).
     */
    public function sendToToken(string $token, string $title, string $body, ?string $url = null): string
    {
        if (! self::configured()) {
            return 'skipped';
        }

        $accessToken = $this->accessToken();
        if (! $accessToken) {
            return 'error';
        }

        $projectId = $this->projectId();

        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_filter([
                    'url' => $url,
                ]),
                'android' => [
                    'priority' => 'high',
                    'notification' => ['default_sound' => true],
                ],
            ],
        ];

        try {
            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $message);

            if ($response->successful()) {
                return 'ok';
            }

            // Jeton non enregistré / invalide : à supprimer côté base.
            if (in_array($response->status(), [400, 403, 404], true)) {
                $errorStatus = $response->json('error.status');
                if (in_array($errorStatus, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                    return 'invalid';
                }
            }

            Log::warning('FCM envoi échoué', ['status' => $response->status(), 'body' => $response->body()]);

            return 'error';
        } catch (\Throwable $e) {
            report($e);

            return 'error';
        }
    }

    /** Jeton d'accès OAuth2 (mis en cache ~55 min). */
    private function accessToken(): ?string
    {
        return Cache::remember('fcm_access_token', 3300, function () {
            $creds = $this->credentials();
            if (! $creds) {
                return null;
            }

            $now = time();
            $jwt = $this->signJwt([
                'iss' => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $creds['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ], $creds['private_key']);

            if (! $jwt) {
                return null;
            }

            try {
                $response = Http::asForm()->timeout(15)->post($creds['token_uri'], [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                return $response->successful() ? $response->json('access_token') : null;
            } catch (\Throwable $e) {
                report($e);

                return null;
            }
        });
    }

    /** Charge et valide le fichier JSON du compte de service. */
    private function credentials(): ?array
    {
        $path = config('push.fcm.credentials_path');
        if (! $path || ! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            return null;
        }

        return [
            'client_email' => $data['client_email'],
            'private_key' => $data['private_key'],
            'token_uri' => $data['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        ];
    }

    /** Signe un JWT RS256 avec la clé privée du compte de service. */
    private function signJwt(array $claims, string $privateKey): ?string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        $segments = [
            $this->base64Url(json_encode($header)),
            $this->base64Url(json_encode($claims)),
        ];
        $signingInput = implode('.', $segments);

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

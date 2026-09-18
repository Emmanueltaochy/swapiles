<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi des notifications push aux iPhone/iPad, directement auprès d'Apple (APNs).
 *
 * POURQUOI PAS FIREBASE ICI : l'application iOS n'embarque pas le SDK Firebase.
 * Le module de notifications de Capacitor s'enregistre donc directement auprès
 * d'Apple, et le jeton reçu est un jeton APNs (64 caractères hexadécimaux), pas
 * un jeton Firebase. Envoyé à Firebase, il était rejeté : c'est la raison pour
 * laquelle l'appareil était bien enregistré mais ne recevait rien.
 *
 * Passer par Apple en direct évite de reconstruire et de resoumettre l'app.
 * Android continue de passer par Firebase (voir FcmService).
 *
 * Authentification : jeton JWT signé en ES256 avec la clé d'authentification
 * APNs (.p8) créée dans le compte développeur Apple. Aucune librairie externe.
 */
class ApnsService
{
    /** Dernier message d'erreur renvoyé par Apple, remonté dans l'administration. */
    public ?string $lastError = null;

    private const PROD_HOST = 'https://api.push.apple.com';
    private const SANDBOX_HOST = 'https://api.sandbox.push.apple.com';

    /** Le push iOS est-il configuré (clé .p8 présente + identifiants renseignés) ? */
    public static function configured(): bool
    {
        $path = config('push.apns.key_path');

        return filled(config('push.apns.key_id'))
            && filled(config('push.apns.team_id'))
            && filled(config('push.apns.bundle_id'))
            && $path && is_file($path);
    }

    /** Le serveur sait-il parler HTTP/2 ? Apple l'exige. */
    public static function http2Available(): bool
    {
        if (! function_exists('curl_version')) {
            return false;
        }

        $version = curl_version();

        return defined('CURL_VERSION_HTTP2')
            && isset($version['features'])
            && ($version['features'] & CURL_VERSION_HTTP2) !== 0;
    }

    /**
     * Envoie une notification à un jeton d'appareil iOS.
     *
     * @return string 'ok' (envoyé), 'invalid' (jeton mort à supprimer),
     *                'skipped' (non configuré) ou 'error' (échec temporaire).
     */
    public function sendToToken(string $token, string $title, string $body, ?string $url = null): string
    {
        $this->lastError = null;

        if (! self::configured()) {
            $this->lastError = "La clé d'authentification Apple (.p8) n'est pas installée sur le serveur.";

            return 'skipped';
        }

        if (! self::http2Available()) {
            $this->lastError = "Ce serveur ne sait pas parler HTTP/2, qu'Apple exige.";

            return 'error';
        }

        $jwt = $this->authToken();
        if (! $jwt) {
            $this->lastError = "Impossible de signer le jeton d'authentification Apple (clé .p8 illisible ?).";

            return 'error';
        }

        $payload = [
            'aps' => [
                'alert' => ['title' => $title, 'body' => $body],
                'sound' => 'default',
                'badge' => 1,
            ],
        ];

        if ($url) {
            $payload['url'] = $url;
        }

        $production = (bool) config('push.apns.production', true);

        $result = $this->post($production ? self::PROD_HOST : self::SANDBOX_HOST, $token, $jwt, $payload);

        // Un binaire TestFlight/App Store parle à l'environnement de production,
        // un binaire installé depuis Xcode au bac à sable. En cas de mauvais
        // environnement, Apple répond « BadDeviceToken » : on tente l'autre une
        // fois plutôt que de supprimer un jeton parfaitement valide.
        if ($result === 'wrong-environment') {
            $result = $this->post($production ? self::SANDBOX_HOST : self::PROD_HOST, $token, $jwt, $payload);

            if ($result === 'wrong-environment') {
                return 'invalid';
            }
        }

        return $result;
    }

    /** @return string 'ok' | 'invalid' | 'wrong-environment' | 'error' */
    private function post(string $host, string $token, string $jwt, array $payload): string
    {
        try {
            $response = Http::withHeaders([
                'authorization' => 'bearer ' . $jwt,
                'apns-topic' => (string) config('push.apns.bundle_id'),
                'apns-push-type' => 'alert',
                'apns-priority' => '10',
            ])
                // Apple n'accepte que HTTP/2.
                ->withOptions(['version' => 2.0])
                ->timeout(15)
                ->withBody(json_encode($payload), 'application/json')
                ->post($host . '/3/device/' . $token);

            if ($response->successful()) {
                return 'ok';
            }

            $reason = (string) $response->json('reason');
            $this->lastError = 'Apple (' . $response->status() . ') : ' . ($reason ?: $response->body());

            // 410 Unregistered : l'app a été désinstallée, le jeton est mort.
            if ($response->status() === 410 || $reason === 'Unregistered') {
                return 'invalid';
            }

            // Jeton émis pour une autre app : il ne sera jamais valide ici.
            if ($reason === 'DeviceTokenNotForTopic') {
                return 'invalid';
            }

            // Jeton valide mais adressé au mauvais environnement : on retentera
            // sur l'autre (voir sendToToken).
            if ($reason === 'BadDeviceToken') {
                return 'wrong-environment';
            }

            Log::warning('APNs envoi échoué', [
                'status' => $response->status(),
                'reason' => $reason,
                'host' => $host,
            ]);

            return 'error';
        } catch (\Throwable $e) {
            report($e);
            $this->lastError = 'Connexion à Apple impossible : ' . mb_substr($e->getMessage(), 0, 300);

            return 'error';
        }
    }

    /**
     * Jeton d'authentification APNs (JWT ES256), valable 1 h côté Apple.
     * Apple refuse les jetons régénérés trop souvent : on le met en cache 50 min.
     */
    private function authToken(): ?string
    {
        return Cache::remember('apns_auth_token', 3000, function () {
            $key = $this->privateKey();
            if (! $key) {
                return null;
            }

            $header = [
                'alg' => 'ES256',
                'kid' => (string) config('push.apns.key_id'),
            ];

            $claims = [
                'iss' => (string) config('push.apns.team_id'),
                'iat' => time(),
            ];

            $input = $this->base64Url(json_encode($header)) . '.' . $this->base64Url(json_encode($claims));

            $der = '';
            if (! openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
                Log::warning('APNs : signature du jeton impossible.');

                return null;
            }

            $signature = $this->derToJose($der);
            if (! $signature) {
                Log::warning('APNs : signature illisible (format DER inattendu).');

                return null;
            }

            return $input . '.' . $this->base64Url($signature);
        });
    }

    /** Clé privée .p8 déposée sur le serveur au déploiement. */
    private function privateKey(): mixed
    {
        $path = config('push.apns.key_path');
        if (! $path || ! is_file($path)) {
            return null;
        }

        $key = openssl_pkey_get_private((string) file_get_contents($path));

        return $key ?: null;
    }

    /**
     * openssl_sign produit une signature ECDSA au format DER (une séquence de
     * deux entiers). Le format JWT attend les deux entiers bruts, sur 32 octets
     * chacun. Sans cette conversion, Apple refuse le jeton.
     */
    private function derToJose(string $der): ?string
    {
        $offset = 0;

        if (($der[$offset] ?? '') !== "\x30") {
            return null;
        }
        $offset++;

        $length = ord($der[$offset] ?? "\x00");
        $offset++;
        if ($length & 0x80) {
            // Forme longue : les octets suivants portent la longueur, on les saute.
            $offset += $length & 0x7f;
        }

        $lire = function () use ($der, &$offset): ?string {
            if (($der[$offset] ?? '') !== "\x02") {
                return null;
            }
            $offset++;

            $len = ord($der[$offset] ?? "\x00");
            $offset++;

            $valeur = substr($der, $offset, $len);
            $offset += $len;

            return $valeur;
        };

        $r = $lire();
        $s = $lire();

        if ($r === null || $s === null) {
            return null;
        }

        // Les entiers DER portent parfois un octet nul de tête (signe positif).
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        if (strlen($r) > 32 || strlen($s) > 32) {
            return null;
        }

        return str_pad($r, 32, "\x00", STR_PAD_LEFT) . str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

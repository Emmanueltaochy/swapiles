<?php

namespace Tests\Feature;

use App\Jobs\SendPushBroadcast;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\ApnsService;
use App\Support\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Aiguillage des notifications push.
 *
 * L'application iOS n'embarque pas le SDK Firebase : le jeton qu'elle
 * enregistre est un jeton Apple (64 caractères hexadécimaux), que Firebase
 * refuse. C'est pour cela que l'appareil apparaissait bien enregistré dans
 * l'admin mais ne recevait aucune notification. Les iPhone sont désormais
 * servis directement par Apple, les Android restent sur Firebase.
 */
class PushRoutingTest extends TestCase
{
    use RefreshDatabase;

    private string $cleApns;

    private string $compteFirebase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Clé EC P-256, du même type que la clé .p8 fournie par Apple.
        $this->cleApns = tempnam(sys_get_temp_dir(), 'apns') . '.p8';
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        openssl_pkey_export($key, $pem);
        file_put_contents($this->cleApns, $pem);

        // Compte de service Firebase factice (clé RSA, comme le vrai fichier).
        $this->compteFirebase = tempnam(sys_get_temp_dir(), 'fcm') . '.json';
        $rsa = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($rsa, $rsaPem);
        file_put_contents($this->compteFirebase, json_encode([
            'project_id' => 'swap-iles',
            'client_email' => 'test@swap-iles.iam.gserviceaccount.com',
            'private_key' => $rsaPem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->cleApns);
        @unlink($this->compteFirebase);
        parent::tearDown();
    }

    private function configurerApns(): void
    {
        config([
            'push.apns.key_path' => $this->cleApns,
            'push.apns.key_id' => 'ABCD123456',
            'push.apns.team_id' => 'Z68H8V9222',
            'push.apns.bundle_id' => 'com.swapiles.app',
            'push.apns.production' => true,
        ]);
    }

    private function configurerFcm(): void
    {
        config([
            'push.fcm.project_id' => 'swap-iles',
            'push.fcm.credentials_path' => $this->compteFirebase,
        ]);
    }

    private function jeton(string $token, ?string $platform): DeviceToken
    {
        return DeviceToken::create([
            'token' => $token,
            'platform' => $platform,
            'last_seen_at' => now(),
        ]);
    }

    private function jetonApns(): string
    {
        return str_repeat('a1b2c3d4', 8); // 64 caractères hexadécimaux
    }

    public function test_un_jeton_ios_part_chez_apple_et_pas_chez_firebase(): void
    {
        $this->configurerApns();
        $this->configurerFcm();
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $this->jeton($this->jetonApns(), 'ios');

        (new SendPushBroadcast('Titre', 'Message', 'https://swapiles.com/annonces'))
            ->handle(new FcmService, new ApnsService);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.push.apple.com/3/device/'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com'));
    }

    public function test_un_jeton_android_part_chez_firebase(): void
    {
        $this->configurerApns();
        $this->configurerFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-test'], 200),
            '*' => Http::response(['name' => 'ok'], 200),
        ]);

        $this->jeton('fMEQ:APA91bHxxxxxxxxxxxxxxxxxxxxxx', 'android');

        (new SendPushBroadcast('Titre', 'Message'))->handle(new FcmService, new ApnsService);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'fcm.googleapis.com'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'push.apple.com'));
    }

    public function test_un_ancien_jeton_sans_plateforme_est_reconnu_comme_apple(): void
    {
        // Jetons enregistrés avant que l'app ne déclare sa plateforme.
        $this->assertTrue(SendPushBroadcast::estIos($this->jeton($this->jetonApns(), null)));
        $this->assertFalse(SendPushBroadcast::estIos($this->jeton('fMEQ:APA91bLong', null)));
    }

    public function test_la_plateforme_declaree_prime_sur_la_forme_du_jeton(): void
    {
        $this->assertFalse(SendPushBroadcast::estIos($this->jeton($this->jetonApns(), 'android')));
        $this->assertTrue(SendPushBroadcast::estIos($this->jeton('jeton-court', 'ios')));
    }

    public function test_apple_recoit_un_jeton_signe_correctement(): void
    {
        $this->configurerApns();
        Http::fake(['*' => Http::response('', 200)]);

        (new ApnsService)->sendToToken($this->jetonApns(), 'Bonjour', 'Message de test');

        Http::assertSent(function ($request) {
            $auth = $request->header('authorization')[0] ?? '';
            $this->assertStringStartsWith('bearer ', $auth);

            $parts = explode('.', substr($auth, 7));
            $this->assertCount(3, $parts, 'Le jeton Apple doit avoir trois parties.');

            $entete = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
            $this->assertSame('ES256', $entete['alg']);
            $this->assertSame('ABCD123456', $entete['kid']);

            $claims = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
            $this->assertSame('Z68H8V9222', $claims['iss']);

            // Apple exige une signature brute de 64 octets (R et S sur 32 octets),
            // pas la forme DER que produit openssl.
            $signature = base64_decode(strtr($parts[2], '-_', '+/'));
            $this->assertSame(64, strlen($signature), 'La signature doit faire 64 octets.');

            $this->assertSame('com.swapiles.app', $request->header('apns-topic')[0] ?? null);

            return true;
        });
    }

    public function test_sans_cle_apple_l_envoi_ios_est_ignore(): void
    {
        config(['push.apns.key_path' => '/chemin/inexistant.p8', 'push.apns.key_id' => null]);

        $this->assertFalse(ApnsService::configured());
        $this->assertSame('skipped', (new ApnsService)->sendToToken($this->jetonApns(), 'A', 'B'));
    }

    public function test_un_jeton_apple_mort_est_supprime(): void
    {
        $this->configurerApns();
        Http::fake(['*' => Http::response(['reason' => 'Unregistered'], 410)]);

        $this->jeton($this->jetonApns(), 'ios');

        (new SendPushBroadcast('Titre', 'Message'))->handle(new FcmService, new ApnsService);

        $this->assertSame(0, DeviceToken::count());
    }

    public function test_un_jeton_valide_n_est_pas_supprime_sur_une_erreur_passagere(): void
    {
        $this->configurerApns();
        Http::fake(['*' => Http::response(['reason' => 'InternalServerError'], 500)]);

        $this->jeton($this->jetonApns(), 'ios');

        (new SendPushBroadcast('Titre', 'Message'))->handle(new FcmService, new ApnsService);

        $this->assertSame(1, DeviceToken::count());
    }

    public function test_le_mauvais_environnement_est_retente_sur_l_autre_serveur_apple(): void
    {
        $this->configurerApns();

        Http::fake([
            'api.push.apple.com/*' => Http::response(['reason' => 'BadDeviceToken'], 400),
            'api.sandbox.push.apple.com/*' => Http::response('', 200),
        ]);

        $resultat = (new ApnsService)->sendToToken($this->jetonApns(), 'A', 'B');

        $this->assertSame('ok', $resultat);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.sandbox.push.apple.com'));
    }

    public function test_une_notification_sans_lien_n_envoie_pas_de_champ_data_vide(): void
    {
        // Bug corrigé : un « data » vide devenait « [] » en JSON — une liste, pas
        // un objet — et Google refusait tout l'envoi avec
        // « Cannot bind a list to map for field 'data' ».
        $this->configurerFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-test'], 200),
            '*' => Http::response(['name' => 'ok'], 200),
        ]);

        (new FcmService)->sendToToken('fMEQ:APA91bTest', 'Titre', 'Message', null);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'fcm.googleapis.com')) {
                return false;
            }

            $this->assertArrayNotHasKey('data', $request->data()['message']);
            $this->assertStringNotContainsString('"data":[]', $request->body());

            return true;
        });
    }

    public function test_une_notification_avec_lien_envoie_un_data_en_objet(): void
    {
        $this->configurerFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-test'], 200),
            '*' => Http::response(['name' => 'ok'], 200),
        ]);

        (new FcmService)->sendToToken('fMEQ:APA91bTest', 'Titre', 'Message', 'https://swapiles.com/annonce/2435');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'fcm.googleapis.com')) {
                return false;
            }

            $this->assertSame(
                ['url' => 'https://swapiles.com/annonce/2435'],
                $request->data()['message']['data']
            );
            // Objet JSON, pas liste.
            $this->assertStringContainsString('"data":{"url":', $request->body());

            return true;
        });
    }

    public function test_le_resultat_et_l_erreur_sont_conserves_sur_l_appareil(): void
    {
        $this->configurerFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-test'], 200),
            '*' => Http::response([
                'error' => ['status' => 'PERMISSION_DENIED', 'message' => 'Cloud Messaging API has not been used'],
            ], 403),
        ]);

        $appareil = $this->jeton('fMEQ:APA91bTest', 'android');

        (new SendPushBroadcast('Titre', 'Message'))->handle(new FcmService, new ApnsService);

        $appareil->refresh();
        $this->assertSame('error', $appareil->last_result);
        $this->assertStringContainsString('Cloud Messaging API has not been used', (string) $appareil->last_error);
        $this->assertNotNull($appareil->last_sent_at);
    }

    public function test_une_erreur_de_permission_ne_supprime_pas_le_jeton(): void
    {
        $this->configurerFcm();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'jeton-test'], 200),
            '*' => Http::response(['error' => ['status' => 'PERMISSION_DENIED', 'message' => 'refus']], 403),
        ]);

        $this->jeton('fMEQ:APA91bTest', 'android');

        (new SendPushBroadcast('Titre', 'Message'))->handle(new FcmService, new ApnsService);

        // Un refus de permission vient de la configuration, pas de l'appareil.
        $this->assertSame(1, DeviceToken::count());
    }

    public function test_sans_compte_de_service_l_erreur_est_explicite(): void
    {
        config(['push.fcm.project_id' => null, 'push.fcm.credentials_path' => '/inexistant.json']);

        $fcm = new FcmService;
        $this->assertSame('skipped', $fcm->sendToToken('tok', 'T', 'B'));
        $this->assertStringContainsString('compte de service Firebase', (string) $fcm->lastError);
    }

    public function test_la_notification_porte_le_titre_le_message_et_le_lien(): void
    {
        $this->configurerApns();
        Http::fake(['*' => Http::response('', 200)]);

        (new ApnsService)->sendToToken($this->jetonApns(), 'Nouvelle annonce', 'Une robe vient d’être publiée', 'https://swapiles.com/annonce/2435');

        Http::assertSent(function ($request) {
            $corps = json_decode($request->body(), true);

            $this->assertSame('Nouvelle annonce', $corps['aps']['alert']['title']);
            $this->assertSame('Une robe vient d’être publiée', $corps['aps']['alert']['body']);
            $this->assertSame('https://swapiles.com/annonce/2435', $corps['url']);

            return true;
        });
    }
}

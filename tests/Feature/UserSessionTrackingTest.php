<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Traçabilité des comptes : capture IP réelle (derrière proxy), user-agent,
 * empreinte d'appareil à l'inscription et à la connexion + détection multicompte.
 */
class UserSessionTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_ip_prefers_cloudflare_header(): void
    {
        $request = Request::create('/', 'POST');
        $request->headers->set('CF-Connecting-IP', '203.0.113.42');

        $this->assertSame('203.0.113.42', UserSession::clientIp($request));
    }

    public function test_device_fingerprint_validation(): void
    {
        $ok = Request::create('/', 'POST', ['device_fingerprint' => 'ABCDEF0123456789abcdef0123456789']);
        $this->assertSame('abcdef0123456789abcdef0123456789', UserSession::deviceFingerprint($ok));

        $bad = Request::create('/', 'POST', ['device_fingerprint' => 'not-a-hash!!']);
        $this->assertNull(UserSession::deviceFingerprint($bad));

        $empty = Request::create('/', 'POST', []);
        $this->assertNull(UserSession::deviceFingerprint($empty));
    }

    public function test_registration_capte_la_vraie_ip_derriere_le_proxy(): void
    {
        // Le proxy est 127.0.0.1 (REMOTE_ADDR en test) ; l'IP client réelle est
        // dans X-Forwarded-For. TrustProxies(at: '*') doit la faire remonter.
        $response = $this->withHeader('X-Forwarded-For', '198.51.100.7')
            ->post(route('register.store'), [
                'name' => 'Multi',
                'email' => 'multi@example.com',
                'password' => 'motdepasse123',
                'password_confirmation' => 'motdepasse123',
                'territoire' => 'La Réunion',
                'device_fingerprint' => 'aaaa1111bbbb2222cccc3333dddd4444',
            ]);

        $response->assertRedirect();

        $session = UserSession::where('event_type', 'registration')->first();
        $this->assertNotNull($session);
        $this->assertSame('198.51.100.7', $session->ip, 'On doit capter l’IP client, pas celle du proxy (127.0.0.1).');
        $this->assertSame('aaaa1111bbbb2222cccc3333dddd4444', $session->device_fingerprint);
    }

    public function test_login_enregistre_une_session(): void
    {
        User::create([
            'name' => 'U', 'email' => 'u@example.com', 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);

        $this->withHeader('X-Forwarded-For', '198.51.100.9')
            ->post(route('login.store'), ['email' => 'u@example.com', 'password' => 'secret1234']);

        $session = UserSession::where('event_type', 'login')->first();
        $this->assertNotNull($session);
        $this->assertSame('198.51.100.9', $session->ip);
    }

    public function test_detection_comptes_lies_par_ip_ou_empreinte(): void
    {
        $a = User::create(['name' => 'A', 'email' => 'a@ex.com', 'password' => bcrypt('x'), 'territoire' => 'La Réunion']);
        $b = User::create(['name' => 'B', 'email' => 'b@ex.com', 'password' => bcrypt('x'), 'territoire' => 'La Réunion']);
        $c = User::create(['name' => 'C', 'email' => 'c@ex.com', 'password' => bcrypt('x'), 'territoire' => 'La Réunion']);
        $z = User::create(['name' => 'Z', 'email' => 'z@ex.com', 'password' => bcrypt('x'), 'territoire' => 'La Réunion']);

        // A et B partagent l'IP ; A et C partagent l'empreinte ; Z n'a rien en commun.
        UserSession::create(['user_id' => $a->id, 'ip' => '10.0.0.1', 'device_fingerprint' => 'fp_aaa', 'event_type' => 'login', 'created_at' => now()]);
        UserSession::create(['user_id' => $b->id, 'ip' => '10.0.0.1', 'device_fingerprint' => 'fp_bbb', 'event_type' => 'login', 'created_at' => now()]);
        UserSession::create(['user_id' => $c->id, 'ip' => '10.9.9.9', 'device_fingerprint' => 'fp_aaa', 'event_type' => 'login', 'created_at' => now()]);
        UserSession::create(['user_id' => $z->id, 'ip' => '172.16.0.1', 'device_fingerprint' => 'fp_zzz', 'event_type' => 'login', 'created_at' => now()]);

        $linked = UserSession::linkedUserIds($a->id)->all();

        sort($linked);
        $this->assertSame([$b->id, $c->id], $linked, 'B (même IP) et C (même empreinte) sont liés à A, pas Z.');
    }
}

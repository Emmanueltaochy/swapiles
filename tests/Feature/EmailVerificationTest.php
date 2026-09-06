<?php

namespace Tests\Feature;

use App\Console\Commands\VerifyLegacyEmails;
use App\Jobs\SendEmailVerification;
use App\Jobs\SendWelcomeEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Confirmation d'adresse e-mail : envoi à l'inscription, contenu de l'e-mail,
 * lien de confirmation, renvoi manuel, et validation d'office des comptes
 * inscrits avant la correction.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** E-mails réellement envoyés (Mail::html/raw ne passe pas par Mail::fake). */
    private \ArrayObject $sent;

    private function captureMails(): \ArrayObject
    {
        $this->sent = new \ArrayObject();

        Event::listen(MessageSent::class, function (MessageSent $e) {
            $this->sent[] = [
                'sujet' => $e->message->getSubject(),
                'html' => (string) $e->message->getHtmlBody(),
                'texte' => (string) $e->message->getTextBody(),
                'to' => collect($e->message->getTo())->map(fn ($a) => $a->getAddress())->all(),
            ];
        });

        return $this->sent;
    }

    public function test_l_inscription_declenche_l_email_de_confirmation(): void
    {
        Queue::fake();

        $this->post('/inscription', [
            'name' => 'Nouveau Membre',
            'email' => 'nouveau@swapiles.test',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'territoire' => 'La Réunion',
        ])->assertRedirect();

        Queue::assertPushed(SendEmailVerification::class);
        Queue::assertPushed(SendWelcomeEmail::class);
    }

    public function test_l_email_de_confirmation_contient_un_lien_utilisable_et_une_version_texte(): void
    {
        $user = User::factory()->create([
            'email' => 'aconfirmer@swapiles.test',
            'email_verified_at' => null,
        ]);

        // On capture APRÈS la création : la création d'un membre déclenche déjà
        // ses propres e-mails, qui ne concernent pas ce test.
        $sent = $this->captureMails();

        (new SendEmailVerification($user->id))->handle();

        $this->assertCount(1, $sent);
        $this->assertSame('Confirmez votre adresse e-mail', $sent[0]['sujet']);
        $this->assertSame(['aconfirmer@swapiles.test'], $sent[0]['to']);

        // Version texte présente : sans elle, l'e-mail part en indésirable.
        $this->assertNotSame('', $sent[0]['texte']);
        $this->assertStringContainsString('/email/verifier/', $sent[0]['texte']);
        $this->assertStringContainsString('/email/verifier/', $sent[0]['html']);

        // Le lien de la version texte doit être cliquable tel quel (pas de &amp;).
        preg_match('#https?://\S+/email/verifier/\S+#', $sent[0]['texte'], $m);
        $this->assertNotEmpty($m, 'Aucun lien de confirmation dans la version texte.');
        $this->assertStringNotContainsString('&amp;', $m[0]);

        $this->get($m[0])->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_l_email_de_bienvenue_a_aussi_une_version_texte_avec_un_lien_valide(): void
    {
        $user = User::factory()->create([
            'email' => 'bienvenue@swapiles.test',
            'email_verified_at' => null,
        ]);

        $sent = $this->captureMails();

        (new SendWelcomeEmail($user->id))->handle();

        $this->assertCount(1, $sent);
        $this->assertNotSame('', $sent[0]['texte']);

        preg_match('#https?://\S+/email/verifier/\S+#', $sent[0]['texte'], $m);
        $this->assertNotEmpty($m, 'Aucun lien de confirmation dans la version texte.');
        $this->assertStringNotContainsString('&amp;', $m[0]);

        $this->get($m[0])->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_le_lien_de_confirmation_marque_le_compte_verifie(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = SendEmailVerification::verificationUrl($user);

        $this->get($url)->assertRedirect(route('login'));
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_un_lien_de_confirmation_non_signe_est_refuse(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->get('/email/verifier/' . $user->id . '/' . sha1($user->email))
            ->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_un_lien_expire_est_refuse(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinute(),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_le_renvoi_manuel_utilise_l_email_de_confirmation_dedie(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect();

        Queue::assertPushed(SendEmailVerification::class);
        Queue::assertNotPushed(SendWelcomeEmail::class);
    }

    public function test_aucun_renvoi_si_l_adresse_est_deja_confirmee(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->post(route('verification.send'))->assertRedirect();

        Queue::assertNotPushed(SendEmailVerification::class);
    }

    public function test_le_job_n_envoie_rien_si_l_adresse_est_deja_confirmee(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $sent = $this->captureMails();
        (new SendEmailVerification($user->id))->handle();

        $this->assertCount(0, $sent);
    }

    public function test_les_comptes_anterieurs_sont_valides_d_office(): void
    {
        $ancien = User::factory()->create([
            'email' => 'ancien@swapiles.test',
            'email_verified_at' => null,
            'created_at' => '2026-05-01 10:00:00',
        ]);

        $this->artisan('users:verify-legacy-emails')->assertExitCode(0);

        $this->assertNotNull($ancien->fresh()->email_verified_at);
    }

    public function test_les_nouvelles_inscriptions_doivent_toujours_confirmer(): void
    {
        $recent = User::factory()->create([
            'email' => 'recent@swapiles.test',
            'email_verified_at' => null,
            'created_at' => \Carbon\Carbon::parse(VerifyLegacyEmails::CUTOFF)->addDay(),
        ]);

        $this->artisan('users:verify-legacy-emails')->assertExitCode(0);

        $this->assertNull($recent->fresh()->email_verified_at);
    }

    public function test_la_validation_d_office_ignore_les_comptes_supprimes(): void
    {
        $supprime = User::factory()->create([
            'email' => 'deleted-42@swapiles.invalid',
            'email_verified_at' => null,
            'created_at' => '2026-05-01 10:00:00',
        ]);

        $this->artisan('users:verify-legacy-emails')->assertExitCode(0);

        $this->assertNull($supprime->fresh()->email_verified_at);
    }

    public function test_le_mode_simulation_ne_modifie_rien(): void
    {
        $ancien = User::factory()->create([
            'email' => 'simulation@swapiles.test',
            'email_verified_at' => null,
            'created_at' => '2026-05-01 10:00:00',
        ]);

        $this->artisan('users:verify-legacy-emails', ['--dry-run' => true])->assertExitCode(0);

        $this->assertNull($ancien->fresh()->email_verified_at);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\SendPushBroadcast;
use App\Jobs\SendTopDressingsCongratsEmails;
use App\Models\Listing;
use App\Models\Notification;
use App\Models\SentEmail;
use App\Models\User;
use App\Support\NotificationPreferences;
use App\Support\PushPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * E-mail de félicitations au Top des dressings.
 */
class TopDressingsCongratsTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_mail_mentionne_le_rang_le_top_et_le_lien(): void
    {
        [$subject, $body] = SendTopDressingsCongratsEmails::buildEmail(
            'Marie', 1, 10, 'https://swapiles.com/meilleurs-dressings'
        );

        $this->assertStringContainsString('Marie', $subject);
        $this->assertStringContainsString('Top 10', $subject);
        $this->assertStringContainsString('n°1', $body);
        $this->assertStringContainsString('Top 10', $body);
        $this->assertStringContainsString('https://swapiles.com/meilleurs-dressings', $body);
    }

    // --- Notification dans l'appli -------------------------------------------
    // Le bouton « Féliciter » n'envoyait que l'e-mail : rien ne sonnait sur
    // le téléphone des vendeurs.

    private function vendeurDuTop(string $email): User
    {
        $vendeur = User::create(['name' => 'Marie', 'email' => $email, 'password' => bcrypt('x'), 'territoire' => 'La Réunion']);
        Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe', 'price' => 20, 'status' => 'published',
            'listing_type' => 'achat', 'territoire' => 'La Réunion', 'views_count' => 300,
        ]);

        return $vendeur;
    }

    private function preparer(): void
    {
        Cache::flush();
        config(['push.fcm.project_id' => 'swap-iles']);
        config()->set('leaderboard.points', ['view' => 1, 'favorite' => 20, 'message' => 10, 'sale' => 25, 'review' => 8]);
    }

    public function test_feliciter_envoie_aussi_une_notification_dans_l_appli(): void
    {
        $this->preparer();
        Queue::fake([SendPushBroadcast::class]);
        $marie = $this->vendeurDuTop('marie@ex.com');

        (new SendTopDressingsCongratsEmails())->handle();

        $notif = Notification::where('user_id', $marie->id)->where('type', 'top_dressing')->sole();
        $this->assertStringContainsString('Top', $notif->title);
        $this->assertStringContainsString('n°1', $notif->message);
        $this->assertSame(route('dressings.top', absolute: false), $notif->url);
        // C'est la notification qui fait sonner le téléphone.
        Queue::assertPushed(SendPushBroadcast::class, fn ($job) => $job->userId === $marie->id);
    }

    public function test_relancer_le_bouton_ne_double_ni_la_notification_ni_l_email(): void
    {
        $this->preparer();
        Queue::fake([SendPushBroadcast::class]);
        $marie = $this->vendeurDuTop('marie@ex.com');

        // E-mail déjà parti aujourd'hui (journal des envois).
        SentEmail::create(['to_email' => 'marie@ex.com', 'subject' => "🏆 Bravo Marie, tu es dans le Top 1 des dressings Swap'Îles !", 'mailer' => 'smtp', 'created_at' => now()]);

        Mail::shouldReceive('raw')->never();
        (new SendTopDressingsCongratsEmails())->handle();
        (new SendTopDressingsCongratsEmails())->handle();

        // La notification part (elle manquait), mais une seule fois.
        $this->assertSame(1, Notification::where('user_id', $marie->id)->where('type', 'top_dressing')->count());
    }

    public function test_la_notification_suit_les_regles_d_animation(): void
    {
        // Heures de silence et plafond quotidien ; réglable par le membre.
        $this->assertSame('animation', PushPolicy::niveau('top_dressing'));
        $this->assertSame('conseils', NotificationPreferences::categorieDuType('top_dressing'));
    }
}

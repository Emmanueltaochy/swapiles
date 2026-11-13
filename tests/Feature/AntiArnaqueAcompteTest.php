<?php

namespace Tests\Feature;

use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Reports\ReportResource;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
use App\Support\MessageModeration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Anti-arnaque « acompte » (avertissement, alerte admin, rappel sur les
 * objets chers) et fiche complète des signalements dans l'admin.
 */
class AntiArnaqueAcompteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Mail::fake();
    }

    private function membre(string $nom, string $email): User
    {
        return User::create(['name' => $nom, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion', 'email_verified_at' => now()]);
    }

    private function admin(): User
    {
        return $this->membre('Emmanuel', 'cabinet@taochyconsulting.fr');
    }

    private function annonce(User $vendeur, float $prix): Listing
    {
        return Listing::create(['user_id' => $vendeur->id, 'title' => 'PS5 avec manettes', 'description' => 'Console en très bon état, peu servi.', 'price' => $prix,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion']);
    }

    public function test_detection_des_demandes_d_acompte(): void
    {
        foreach ([
            'Il faut un acompte de 100€ pour venir la voir',
            'je demande des arrhes avant',
            'Envoie 100 euros d avance et je te la réserve',
            'tu me verses 50e pour la réservation stp',
            'paye le déplacement avant de venir',
            'il me faut une caution de 100 € avant de te voir',
        ] as $texte) {
            $this->assertTrue(MessageModeration::detectAcompte($texte), $texte);
        }

        foreach ([
            'Bonjour, elle est toujours dispo ?',
            'Je peux réserver pour samedi ?',
            'On se voit en avance à 14h ?',
            'Le prix est de 300 euros',
            '',
        ] as $texte) {
            $this->assertFalse(MessageModeration::detectAcompte($texte), $texte);
        }
    }

    public function test_le_message_part_l_acheteur_est_averti_et_l_admin_alerte_une_fois(): void
    {
        $admin = $this->admin();
        $vendeur = $this->membre('Vendeur', 'v@ex.com');
        $acheteur = $this->membre('Acheteur', 'a@ex.com');
        $ps5 = $this->annonce($vendeur, 350);

        $route = route('account.messages.store', ['listing' => $ps5, 'user' => $acheteur]);
        $this->actingAs($vendeur)->post($route, ['body' => 'Il me faut un acompte de 100€ avant de se voir'])->assertRedirect();
        $this->actingAs($vendeur)->post($route, ['body' => 'Alors pour l acompte ?'])->assertRedirect();

        // Pas bloqué, mais signalé.
        $this->assertSame(2, Message::count());
        $this->assertSame(2, Message::where('flag_kind', 'acompte')->count());

        // Une seule notification pour l'acheteur, une seule alerte pour l'admin.
        $this->assertSame(1, Notification::where('user_id', $acheteur->id)->where('type', 'moderation_acompte')->count());
        $this->assertSame(1, Notification::where('user_id', $admin->id)->where('type', 'moderation_acompte')->count());
        $this->assertStringContainsString('PS5', Notification::where('user_id', $admin->id)->value('title'));

        // L'avertissement est visible pour l'acheteur, pas pour le vendeur.
        $this->actingAs($acheteur)->get(route('account.messages.show', ['listing' => $ps5, 'user' => $vendeur]))
            ->assertOk()->assertSee('Attention : demande d’acompte', false);
        $this->actingAs($vendeur)->get(route('account.messages.show', ['listing' => $ps5, 'user' => $acheteur]))
            ->assertOk()->assertDontSee('Attention : demande d’acompte', false);
    }

    public function test_un_message_normal_n_est_pas_signale(): void
    {
        $vendeur = $this->membre('Vendeur', 'v@ex.com');
        $acheteur = $this->membre('Acheteur', 'a@ex.com');
        $ps5 = $this->annonce($vendeur, 350);

        $this->actingAs($acheteur)->post(route('account.messages.store', ['listing' => $ps5, 'user' => $vendeur]), ['body' => 'Bonjour, toujours dispo ?'])->assertRedirect();

        $this->assertNull(Message::sole()->flag_kind);
        $this->assertSame(0, Notification::where('type', 'moderation_acompte')->count());
    }

    public function test_rappel_securite_sur_les_objets_chers_seulement(): void
    {
        $vendeur = $this->membre('Vendeur', 'v@ex.com');
        $acheteur = $this->membre('Acheteur', 'a@ex.com');
        $chere = $this->annonce($vendeur, 350);
        $petite = $this->annonce($vendeur, 20);

        $this->actingAs($acheteur)->get(route('listings.show', $chere))->assertOk()->assertSee('Achetez en toute sécurité');
        $this->actingAs($acheteur)->get(route('listings.show', $petite))->assertOk()->assertDontSee('Achetez en toute sécurité');
        $this->actingAs($vendeur)->get(route('listings.show', $chere))->assertOk()->assertDontSee('Achetez en toute sécurité');
    }

    public function test_fiche_complete_d_un_signalement_dans_l_admin(): void
    {
        $admin = $this->admin();
        $lulu = $this->membre('Lulu97129', 'lulu@ex.com');
        $plaignante = $this->membre('Marie', 'marie@ex.com');
        Message::create(['sender_id' => $lulu->id, 'receiver_id' => $plaignante->id, 'body' => 'Je sais où tu habites']);
        $long = 'Personne non sérieuse qui possède ma localisation et qui insiste depuis trois jours pour venir chez moi sans prévenir.';
        $report = Report::create(['reporter_id' => $plaignante->id, 'reportable_type' => User::class, 'reportable_id' => $lulu->id,
            'reason' => 'harcelement', 'details' => $long, 'status' => 'open']);
        Report::create(['reporter_id' => $admin->id, 'reportable_type' => User::class, 'reportable_id' => $lulu->id,
            'reason' => 'arnaque', 'details' => 'Ancien signalement', 'status' => 'reviewed']);

        $fiche = ReportResource::fiche($report);
        $this->assertSame($lulu->id, $fiche['membre']['id']);
        $this->assertSame(2, $fiche['membre']['signalements_recus']);
        $this->assertCount(1, $fiche['autres']);
        $this->assertCount(1, $fiche['messages']);

        $html = view('filament.reports.fiche', $fiche)->render();
        $this->assertStringContainsString($long, $html);
        $this->assertStringContainsString('lulu@ex.com', $html);
        $this->assertStringContainsString('Je sais où tu habites', $html);
        $this->assertStringContainsString('Ancien signalement', $html);

        $this->actingAs($admin);
        $this->get('/admin/reports')->assertOk()->assertSee('Lulu97129');

        Livewire::test(ListReports::class)
            ->mountTableAction('voir', $report)
            ->assertMountedActionModalSee(['Je sais où tu habites', 'Bannir le membre signalé']);

        ReportResource::bannir($report);
        $this->assertTrue($lulu->fresh()->is_banned);
        $this->assertSame('reviewed', $report->fresh()->status);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Signalement de contenu + blocage de membre (exigence des stores Apple/Google
 * pour toute app avec du contenu généré par les utilisateurs).
 */
class ReportAndBlockTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email): User
    {
        return User::create([
            'name' => 'U ' . $email, 'email' => $email, 'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
    }

    private function listing(User $seller): Listing
    {
        return Listing::create([
            'user_id' => $seller->id, 'title' => 'Vélo', 'price' => 30,
            'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
            'requires_online_payment' => false, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
    }

    /* ---------------------------------------------------------------- Signalement */

    public function test_un_membre_peut_signaler_une_annonce(): void
    {
        $seller = $this->user('seller@ex.com');
        $reporter = $this->user('reporter@ex.com');
        $listing = $this->listing($seller);

        $this->actingAs($reporter)->post(route('reports.listing', $listing), [
            'reason' => 'contrefacon',
            'details' => 'Fausse marque évidente.',
        ])->assertRedirect();

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertSame($reporter->id, $report->reporter_id);
        $this->assertSame(Listing::class, $report->reportable_type);
        $this->assertSame($listing->id, (int) $report->reportable_id);
        $this->assertSame('contrefacon', $report->reason);
        $this->assertSame('open', $report->status);
    }

    public function test_un_membre_peut_signaler_un_autre_membre(): void
    {
        $target = $this->user('target@ex.com');
        $reporter = $this->user('reporter2@ex.com');

        $this->actingAs($reporter)->post(route('reports.user', $target), [
            'reason' => 'harcelement',
        ])->assertRedirect();

        $report = Report::first();
        $this->assertNotNull($report);
        $this->assertSame(User::class, $report->reportable_type);
        $this->assertSame($target->id, (int) $report->reportable_id);
    }

    public function test_le_motif_est_obligatoire_et_valide(): void
    {
        $target = $this->user('target2@ex.com');
        $reporter = $this->user('reporter3@ex.com');

        $this->actingAs($reporter)->post(route('reports.user', $target), [
            'reason' => 'motif_bidon',
        ])->assertSessionHasErrors('reason');

        $this->assertSame(0, Report::count());
    }

    public function test_on_ne_peut_pas_signaler_sa_propre_annonce(): void
    {
        $seller = $this->user('self@ex.com');
        $listing = $this->listing($seller);

        $this->actingAs($seller)->post(route('reports.listing', $listing), [
            'reason' => 'spam',
        ])->assertRedirect();

        $this->assertSame(0, Report::count());
    }

    public function test_deux_signalements_du_meme_membre_sur_la_meme_cible_ne_font_qu_un(): void
    {
        $target = $this->user('t3@ex.com');
        $reporter = $this->user('r5@ex.com');

        $this->actingAs($reporter)->post(route('reports.user', $target), ['reason' => 'spam']);
        $this->actingAs($reporter)->post(route('reports.user', $target), ['reason' => 'arnaque']);

        $this->assertSame(1, Report::count());
        $this->assertSame('arnaque', Report::first()->reason);
    }

    /* --------------------------------------------------------------------- Blocage */

    public function test_bloquer_puis_debloquer_un_membre(): void
    {
        $me = $this->user('me@ex.com');
        $other = $this->user('other@ex.com');

        $this->actingAs($me)->post(route('users.block.toggle', $other))->assertRedirect();
        $this->assertTrue($me->fresh()->hasBlocked($other));

        $this->actingAs($me)->post(route('users.block.toggle', $other))->assertRedirect();
        $this->assertFalse($me->fresh()->hasBlocked($other));
    }

    public function test_un_membre_bloque_ne_peut_plus_envoyer_de_message(): void
    {
        $me = $this->user('me2@ex.com');
        $other = $this->user('other2@ex.com');

        // me bloque other.
        $me->blockedUsers()->attach($other->id);

        // other tente d'écrire à me : refusé, aucun message créé.
        $this->actingAs($other)->post(route('account.messages.store.general', $me), [
            'body' => 'Coucou',
        ])->assertRedirect();

        $this->assertSame(0, Message::count());
    }

    public function test_je_ne_peux_pas_ecrire_a_un_membre_que_j_ai_bloque(): void
    {
        $me = $this->user('me3@ex.com');
        $other = $this->user('other3@ex.com');

        $me->blockedUsers()->attach($other->id);

        $this->actingAs($me)->post(route('account.messages.store.general', $other), [
            'body' => 'Coucou',
        ])->assertRedirect();

        $this->assertSame(0, Message::count());
    }

    public function test_un_message_normal_passe_sans_blocage(): void
    {
        $me = $this->user('me4@ex.com');
        $other = $this->user('other4@ex.com');

        $this->actingAs($me)->post(route('account.messages.store.general', $other), [
            'body' => 'Bonjour, dispo ?',
        ])->assertRedirect();

        $this->assertSame(1, Message::count());
    }
}

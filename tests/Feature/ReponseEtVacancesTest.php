<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use App\Support\TempsReponse;
use App\Support\Vacances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReponseEtVacancesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $nom): User
    {
        return User::create(['name' => $nom, 'email' => strtolower($nom) . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function annonce(User $vendeur, string $statut = 'published'): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe ' . uniqid(), 'description' => 'Description de test assez longue.',
            'price' => 20, 'status' => $statut, 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
    }

    /** Un acheteur écrit à $heure, le vendeur répond $minutes plus tard (null = jamais). */
    private function echange(User $vendeur, User $acheteur, Listing $annonce, string $heure, ?int $minutes, bool $relance = false): void
    {
        Carbon::setTestNow($heure);
        Message::create(['sender_id' => $acheteur->id, 'receiver_id' => $vendeur->id, 'listing_id' => $annonce->id, 'body' => 'Dispo ?']);
        if ($relance) {
            Carbon::setTestNow(Carbon::parse($heure)->addMinutes(5));
            Message::create(['sender_id' => $acheteur->id, 'receiver_id' => $vendeur->id, 'listing_id' => $annonce->id, 'body' => 'Allo ?']);
        }
        if ($minutes !== null) {
            Carbon::setTestNow(Carbon::parse($heure)->addMinutes($minutes));
            Message::create(['sender_id' => $vendeur->id, 'receiver_id' => $acheteur->id, 'listing_id' => $annonce->id, 'body' => 'Oui !']);
        }
    }

    public function test_delai_de_reponse_median_et_libelles(): void
    {
        $lea = $this->membre('Lea');
        $annonce = $this->annonce($lea);
        $acheteurs = collect(['Tom', 'Zoe', 'Max', 'Ana'])->map(fn ($n) => $this->membre($n));

        // 3 mesures : 10 min, 30 min (la relance ne remet pas le compteur à zéro), 50 min.
        $this->echange($lea, $acheteurs[0], $annonce, '2026-10-10 10:00', 10);
        $this->echange($lea, $acheteurs[1], $annonce, '2026-10-11 10:00', 30, true);
        $this->echange($lea, $acheteurs[2], $annonce, '2026-10-12 10:00', 50);
        Carbon::setTestNow('2026-10-20 10:00');

        $this->assertSame(['minutes' => 30, 'mesures' => 3], TempsReponse::calculer($lea));
        $this->assertTrue(TempsReponse::libelle(30)['rapide']);
        $this->assertSame('Répond en quelques heures', TempsReponse::libelle(180)['texte']);
        $this->assertNull(TempsReponse::libelle(3000), 'Plus d’un jour : on ne dit rien.');

        // Un acheteur ignoré depuis plus de 24 h compte comme 72 h.
        $this->echange($lea, $acheteurs[3], $annonce, '2026-10-15 10:00', null);
        Carbon::setTestNow('2026-10-20 10:00');
        $this->assertSame(40, TempsReponse::calculer($lea)['minutes'], 'Médiane de 10, 30, 50, 4320.');

        TempsReponse::recalculerTout();
        $this->assertSame(40, $lea->fresh()->reponse_minutes);
        $this->get(route('profiles.show', $lea))->assertOk()->assertSee('Répond vite');
        $this->get(route('listings.show', $annonce))->assertOk()->assertSee('Répond vite');
    }

    public function test_moins_de_3_mesures_pas_de_badge(): void
    {
        $lea = $this->membre('Lea');
        $this->echange($lea, $this->membre('Tom'), $this->annonce($lea), '2026-10-10 10:00', 5);
        Carbon::setTestNow('2026-10-20 10:00');

        $this->assertSame(['minutes' => null, 'mesures' => 1], TempsReponse::calculer($lea));
        TempsReponse::recalculerTout();
        $this->get(route('profiles.show', $lea))->assertOk()->assertDontSee('Répond vite');
    }

    public function test_mode_vacances_de_bout_en_bout(): void
    {
        Carbon::setTestNow('2026-10-20 10:00');
        $lea = $this->membre('Lea');
        $tom = $this->membre('Tom');
        $enLigne = $this->annonce($lea);
        $brouillon = $this->annonce($lea, 'draft');
        $vendue = $this->annonce($lea, 'sold');

        // Date refusée : aujourd'hui, ou plus de 60 jours.
        $this->actingAs($lea)->post(route('account.vacances.activer'), ['retour' => '2026-10-20'])->assertSessionHasErrors('retour');
        $this->post(route('account.vacances.activer'), ['retour' => '2027-01-30'])->assertSessionHasErrors('retour');

        $this->post(route('account.vacances.activer'), ['retour' => '2026-11-12', 'message' => 'En métropole, je reviens vite'])
            ->assertRedirect(route('account.dashboard'));
        $this->assertTrue(Vacances::enVacances($lea->fresh()));
        $this->assertSame('vacances', $enLigne->fresh()->status);
        $this->assertSame('draft', $brouillon->fresh()->status, 'Les brouillons ne bougent pas.');
        $this->assertSame('sold', $vendue->fresh()->status);

        // Publier pendant les vacances : l'annonce attend le retour.
        $this->patch(route('account.listings.publish', $brouillon));
        $this->assertSame('vacances', $brouillon->fresh()->status);

        // Côté acheteur : visible avec un bandeau, mais ni achat ni offre.
        $this->actingAs($tom)->get(route('listings.show', $enLigne))->assertOk()
            ->assertSee('est en vacances')->assertSee('jeudi 12 novembre')->assertSee('En métropole, je reviens vite');
        $this->post(route('checkout.start', $enLigne))->assertRedirect(route('listings.show', $enLigne));
        $this->assertSame(0, \App\Models\Transaction::count());
        $this->post(route('offers.store', $enLigne), ['amount' => 15])->assertRedirect(route('listings.show', $enLigne));
        $this->assertSame(0, \App\Models\ListingOffer::count());
        $this->get(route('profiles.show', $lea))->assertOk()->assertSee('En vacances');

        // Le jour du retour, tout repart tout seul.
        Carbon::setTestNow('2026-11-12 08:00');
        $this->assertSame(1, Vacances::terminerLesRetours());
        $this->assertNull($lea->fresh()->vacances_jusqu_au);
        $this->assertSame('published', $enLigne->fresh()->status);
        $this->assertSame('published', $brouillon->fresh()->status);
    }

    public function test_revenir_plus_tot(): void
    {
        Carbon::setTestNow('2026-10-20 10:00');
        $lea = $this->membre('Lea');
        $annonce = $this->annonce($lea);

        $this->actingAs($lea)->post(route('account.vacances.activer'), ['retour' => '2026-10-30']);
        $this->get(route('account.dashboard'))->assertOk()->assertSee('Je suis de retour');
        $this->get(route('account.settings'))->assertOk()->assertSee('Mode vacances');

        $this->delete(route('account.vacances.terminer'))->assertRedirect(route('account.dashboard'));
        $this->assertSame('published', $annonce->fresh()->status);
        $this->assertFalse(Vacances::enVacances($lea->fresh()));
    }
}

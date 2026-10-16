<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Notification;
use App\Models\User;
use App\Support\NouveautesDressings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NouveautesDressingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function membre(string $nom): User
    {
        return User::create(['name' => $nom, 'email' => strtolower($nom) . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function annonce(User $vendeur): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe ' . uniqid(), 'description' => 'Description de test assez longue.',
            'price' => 20, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
    }

    public function test_recap_du_soir_a_18_h_heure_de_la_reunion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 10:00', 'Indian/Reunion'));
        $moi = $this->membre('Moi');
        [$lea, $tom, $max, $zoe] = [$this->membre('Lea'), $this->membre('Tom'), $this->membre('Max'), $this->membre('Zoe')];
        $moi->followedSellers()->attach([$lea->id, $tom->id, $max->id]);
        $this->annonce($lea);
        $this->annonce($lea);
        $this->annonce($tom);
        $this->annonce($zoe); // pas suivie
        // Max : déjà vu en story.
        $this->annonce($max);
        DB::table('story_vues')->insert(['user_id' => $moi->id, 'vendeur_id' => $max->id, 'vu_jusqu_a' => now()]);

        $this->assertSame(0, NouveautesDressings::envoyer(), 'Pas avant 18 h chez le membre.');

        Carbon::setTestNow(Carbon::parse('2026-10-20 18:05', 'Indian/Reunion'));
        $this->assertSame(1, NouveautesDressings::envoyer());

        $notif = Notification::where('type', 'dressings_nouveautes')->sole();
        $this->assertSame($moi->id, $notif->user_id);
        $this->assertSame('/?stories=1', $notif->url);
        $this->assertSame('Lea et Tom ont publié 3 nouveaux articles. Touchez pour les voir en story.', $notif->message);

        // Pas deux récaps le même soir.
        $this->assertSame(0, NouveautesDressings::envoyer());
    }

    public function test_rien_si_moins_de_2_nouveautes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 18:05', 'Indian/Reunion'));
        $moi = $this->membre('Moi');
        $lea = $this->membre('Lea');
        $moi->followedSellers()->attach($lea->id);
        $this->annonce($lea);

        $this->assertSame(0, NouveautesDressings::envoyer());
        $this->assertSame(0, Notification::count());
    }

    public function test_message_avec_plusieurs_dressings(): void
    {
        $msg = NouveautesDressings::message(['vendeurs' => collect(['Lea', 'Tom', 'Max', 'Zoe']), 'articles' => 9]);
        $this->assertSame('Lea, Tom et 2 autres dressings ont publié 9 nouveaux articles. Touchez pour les voir en story.', $msg);
        $this->assertStringStartsWith('Lea a publié 2', NouveautesDressings::message(['vendeurs' => collect(['Lea']), 'articles' => 2]));
    }

    public function test_une_seule_alerte_instantanee_par_dressing_et_par_jour(): void
    {
        Queue::fake();
        $lea = $this->membre('Lea');
        $moi = $this->membre('Moi');
        $moi->followedSellers()->attach($lea->id);

        $this->assertTrue(NouveautesDressings::alerteInstantanee($moi->id, $lea->id));
        $this->assertFalse(NouveautesDressings::alerteInstantanee($moi->id, $lea->id), 'Le 2e article du jour ne réalerte pas.');

        Carbon::setTestNow(now()->addDay());
        $this->assertTrue(NouveautesDressings::alerteInstantanee($moi->id, $lea->id), 'Le lendemain, à nouveau.');
    }

    public function test_la_notification_ouvre_les_stories(): void
    {
        $lea = $this->membre('Lea');
        $this->annonce($lea);

        $this->withCookie('territoire', 'reunion')->get('/?stories=1')->assertOk()
            ->assertSee('ouvrirDepuisNotification', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EspaceCompteRevueTest extends TestCase
{
    use RefreshDatabase;

    private function membre(string $nom = 'Léa'): User
    {
        return User::create(['name' => $nom, 'email' => strtolower($nom) . uniqid() . '@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function annonce(User $vendeur, string $statut = 'published'): Listing
    {
        return Listing::create([
            'user_id' => $vendeur->id, 'title' => 'Robe ' . uniqid(), 'description' => 'Description de test assez longue.',
            'price' => 20, 'status' => $statut, 'listing_type' => 'achat', 'territoire' => 'La Réunion',
        ]);
    }

    public function test_mon_compte_montre_6_annonces_et_compte_toutes_les_annonces(): void
    {
        $lea = $this->membre();
        foreach (range(1, 30) as $i) {
            $this->annonce($lea);
        }
        $this->annonce($lea, 'draft');
        $this->annonce($lea, 'sold');

        $reponse = $this->actingAs($lea)->get(route('account.dashboard'))->assertOk();
        $reponse->assertSeeInOrder(['30 actives', '1 brouillon', '1 vendue'])
            ->assertSee('Voir mes 32 annonces');
        $this->assertSame(6, $reponse->viewData('listings')->count());

        $toutes = $this->get(route('account.dashboard', ['annonces' => 'toutes']))->assertOk();
        $this->assertSame(24, $toutes->viewData('listings')->count(), 'Liste complète, paginée par 24.');
        $toutes->assertSee('Brouillons · 1', false);

        $brouillons = $this->get(route('account.dashboard', ['annonces' => 'toutes', 'etat' => 'draft']))->assertOk();
        $this->assertSame(1, $brouillons->viewData('listings')->count());
    }

    public function test_ouvrir_la_liste_ne_marque_plus_les_messages_comme_lus(): void
    {
        $lea = $this->membre();
        $tom = $this->membre('Tom');
        $annonce = $this->annonce($lea);
        Message::create(['sender_id' => $tom->id, 'receiver_id' => $lea->id, 'listing_id' => $annonce->id, 'body' => 'Toujours dispo ?']);
        Message::create(['sender_id' => $tom->id, 'receiver_id' => $lea->id, 'listing_id' => $annonce->id, 'body' => 'Je peux passer samedi']);

        $this->actingAs($lea)->get(route('account.messages.index'))
            ->assertOk()
            ->assertSee('2 non lus', false);
        $this->assertSame(2, Message::whereNull('read_at')->count(), 'La liste seule ne lit rien.');

        $this->get(route('account.messages.show', ['listing' => $annonce, 'user' => $tom]))->assertOk();
        $this->assertSame(0, Message::whereNull('read_at')->count(), 'Ouvrir la conversation la marque lue.');
    }

    public function test_une_seule_adresse_et_des_libelles_en_francais(): void
    {
        $lea = $this->membre();

        $this->actingAs($lea)->get(route('account.settings'))->assertOk()
            ->assertSee('Mon adresse')
            ->assertDontSee('Mes adresses de livraison');

        $this->get(route('account.profile.edit'))->assertOk()
            ->assertSee('Choisir une photo')
            ->assertDontSee('name="address_line1"', false);

        $this->get(route('account.wallet.index'))->assertOk()
            ->assertSee('Mon argent')
            ->assertDontSee('Mon wallet');
    }

    public function test_photo_de_profil_depuis_mon_compte(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $lea = $this->membre();

        $this->actingAs($lea)->get(route('account.dashboard'))->assertOk()
            ->assertSee('Mettre une photo de profil')
            ->assertSee(route('account.profile.photo'), false);

        $this->post(route('account.profile.photo'), [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->image('moi.jpg', 400, 400),
        ])->assertRedirect(route('account.dashboard'));

        $premiere = $lea->fresh()->avatar;
        $this->assertStringStartsWith('/storage/avatars/', $premiere);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists(substr($premiere, strlen('/storage/')));

        $this->get(route('account.dashboard'))->assertSee('Modifier la photo de profil');

        // Nouvelle photo : l'ancienne est effacée.
        $this->post(route('account.profile.photo'), [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->image('moi2.jpg'),
        ]);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing(substr($premiere, strlen('/storage/')));

        // Pas une image : refusé, la photo ne change pas.
        $actuelle = $lea->fresh()->avatar;
        $this->post(route('account.profile.photo'), [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('avatar');
        $this->assertSame($actuelle, $lea->fresh()->avatar);
    }

    public function test_bio_sous_le_nom_avec_garde_fous(): void
    {
        $lea = $this->membre();

        $this->actingAs($lea)->get(route('account.dashboard'))->assertOk()->assertSee('Ajouter une bio');

        $this->patch(route('account.profile.bio'), ['bio' => "  Maman de 3 🌴  \n\n\nEnvoi rapide  "])
            ->assertRedirect(route('account.dashboard'));
        $this->assertSame("Maman de 3 🌴\nEnvoi rapide", $lea->fresh()->bio, 'Espaces et lignes vides nettoyés.');

        $this->get(route('account.dashboard'))->assertSee('Maman de 3 🌴')->assertSee('Modifier la bio');
        $this->get(route('profiles.show', $lea))->assertOk()->assertSee('Maman de 3 🌴');

        foreach ([
            'Appelle-moi au 0692 12 34 56',
            'Écris-moi sur WhatsApp',
            'Tout mon stock sur www.monsite.com',
            'Contact : lea@gmail.com',
            'Paiement par PayPal possible',
            str_repeat('a', 151),
        ] as $refusee) {
            $this->patch(route('account.profile.bio'), ['bio' => $refusee])->assertSessionHasErrors('bio');
            $this->assertSame("Maman de 3 🌴\nEnvoi rapide", $lea->fresh()->bio, "Refusée : {$refusee}");
        }

        // Bio vidée = retirée.
        $this->patch(route('account.profile.bio'), ['bio' => '   ']);
        $this->assertNull($lea->fresh()->bio);
    }

    public function test_boutons_plus_blancs(): void
    {
        $lea = $this->membre();
        $tom = $this->membre('Tom');

        $this->actingAs($lea)->get(route('profiles.show', $tom))->assertOk()
            ->assertDontSee('➕', false)
            ->assertSee('Suivre');
        $this->get(route('account.dashboard'))->assertDontSee('➕', false)->assertSee('Voir mon profil');
        $this->assertStringNotContainsString('➕', file_get_contents(public_path('js/follow.js')));
    }

    public function test_la_photo_de_profil_s_agrandit_au_toucher(): void
    {
        $tom = $this->membre('Tom');
        $this->get(route('profiles.show', $tom))->assertOk()->assertDontSee('data-photo-agrandir', false);

        $tom->forceFill(['avatar' => '/storage/avatars/tom.jpg'])->save();
        $this->get(route('profiles.show', $tom))->assertOk()
            ->assertSee('data-photo-agrandir', false)
            ->assertSee('Agrandir la photo de Tom', false)
            ->assertSee('data-photo-grande', false);
    }
}

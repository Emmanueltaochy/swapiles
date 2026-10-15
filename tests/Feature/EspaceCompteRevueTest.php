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
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Transactions\Tables\TransactionsTable;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TableauDeBord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ventes en main propre : « Paiement espèces reçu » n'est que la parole du
 * vendeur (parfois une annonce simplement retirée). L'admin voit le niveau de
 * preuve de chaque vente, l'acheteur désigné confirme ou conteste, et le
 * tableau de bord sépare l'argent réellement passé par Swap'Îles.
 */
class VenteDeclareeTest extends TestCase
{
    use RefreshDatabase;

    private User $vendeur;

    private User $acheteur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendeur = $this->membre('vendeur@ex.com', 'Vendeuse Marie');
        $this->acheteur = $this->membre('acheteur@ex.com', 'Acheteur Paul');
    }

    private function membre(string $email, string $nom = 'M'): User
    {
        return User::create(['name' => $nom, 'email' => $email, 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
    }

    private function annonce(float $prix = 30): Listing
    {
        return Listing::create([
            'user_id' => $this->vendeur->id, 'title' => 'Vélo ' . $prix, 'description' => 'Description de test assez longue.',
            'price' => $prix, 'currency' => 'EUR', 'listing_type' => 'achat', 'status' => 'published', 'territoire' => 'La Réunion',
            'requires_online_payment' => false, 'allows_hand_delivery' => true, 'pickup_enabled' => true,
        ]);
    }

    private function vente(float $montant, string $mode, ?User $acheteur = null, array $plus = []): Transaction
    {
        return Transaction::create([
            'listing_id' => $this->annonce($montant)->id, 'seller_id' => $this->vendeur->id, 'buyer_id' => $acheteur?->id,
            'amount' => $montant, 'seller_amount' => $montant, 'commission' => $mode === 'cb' ? 1.5 : 0,
            'buyer_protection_fee' => $mode === 'cb' ? 1.4 : 0, 'shipping_fee' => 0, 'currency' => 'EUR',
            'payment_method' => $mode, 'delivery_method' => 'hand_delivery', 'status' => 'completed', 'shipping_status' => 'hand_delivered',
        ] + $plus);
    }

    // --- Niveau de preuve ---------------------------------------------------

    public function test_niveaux_de_fiabilite(): void
    {
        $this->assertSame('en_ligne', $this->vente(20, 'cb', $this->acheteur)->fiabilite());
        $this->assertSame('declaree_inconnu', $this->vente(20, 'especes')->fiabilite());
        $this->assertSame('declaree_membre', $this->vente(20, 'especes', $this->acheteur)->fiabilite());
        $this->assertSame('confirmee', $this->vente(20, 'especes', $this->acheteur, ['acheteur_confirme_at' => now()])->fiabilite());
        $this->assertSame('contestee', $this->vente(20, 'especes', $this->acheteur, ['acheteur_conteste_at' => now()])->fiabilite());
        $this->assertSame('echange', $this->vente(0, 'echange')->fiabilite());
        $this->assertSame('don', $this->vente(0, 'don')->fiabilite());
    }

    public function test_le_filtre_admin_suit_la_meme_regle(): void
    {
        $this->vente(20, 'cb', $this->acheteur);
        $this->vente(20, 'especes');
        $this->vente(20, 'especes');
        $this->vente(20, 'especes', $this->acheteur);
        $this->vente(20, 'especes', $this->acheteur, ['acheteur_confirme_at' => now()]);
        $this->vente(20, 'especes', $this->acheteur, ['acheteur_conteste_at' => now()]);
        $this->vente(0, 'don');

        foreach (array_keys(Transaction::FIABILITES) as $cle) {
            $parFiltre = TransactionsTable::filtrerFiabilite(Transaction::query(), $cle)->pluck('id')->sort()->values()->all();
            $parModele = Transaction::all()->filter(fn ($t) => $t->fiabilite() === $cle)->pluck('id')->sort()->values()->all();
            $this->assertSame($parModele, $parFiltre, "Filtre « {$cle} »");
        }
        $this->assertCount(2, TransactionsTable::filtrerFiabilite(Transaction::query(), 'declaree_inconnu')->get());
    }

    // --- L'acheteur désigné confirme ou conteste ----------------------------

    public function test_l_acheteur_designe_est_prevenu(): void
    {
        $annonce = $this->annonce(30);
        $this->acheteur->favorites()->attach($annonce->id);

        $this->actingAs($this->vendeur)->patch(route('account.listings.cash-paid', $annonce), ['buyer_id' => $this->acheteur->id]);

        $notif = Notification::where('user_id', $this->acheteur->id)->sole();
        $this->assertSame('cash_sale_confirm', $notif->type);
        $this->assertStringContainsString('Vendeuse Marie', $notif->message);
        $this->assertSame(route('account.transactions.show', Transaction::sole(), absolute: false), $notif->url);
    }

    public function test_vente_hors_swapiles_ne_previent_personne(): void
    {
        $this->actingAs($this->vendeur)->patch(route('account.listings.cash-paid', $this->annonce()), []);

        $this->assertSame(0, Notification::count());
    }

    public function test_l_acheteur_confirme(): void
    {
        $vente = $this->vente(30, 'especes', $this->acheteur);

        $this->actingAs($this->acheteur)->get(route('account.transactions.show', $vente))
            ->assertOk()->assertSee('Avez-vous bien acheté cet article ?');

        $this->actingAs($this->acheteur)->patch(route('account.transactions.confirm-purchase', $vente))->assertRedirect();

        $this->assertSame('confirmee', $vente->fresh()->fiabilite());
        $this->actingAs($this->acheteur)->get(route('account.transactions.show', $vente))
            ->assertDontSee('Avez-vous bien acheté cet article ?');
        // Une seule réponse possible.
        $this->actingAs($this->acheteur)->patch(route('account.transactions.dispute-purchase', $vente))->assertForbidden();
    }

    public function test_l_acheteur_conteste_et_aucun_avis_n_est_possible(): void
    {
        $vente = $this->vente(30, 'especes', $this->acheteur);

        $this->actingAs($this->acheteur)->patch(route('account.transactions.dispute-purchase', $vente))
            ->assertRedirect(route('account.transactions.index'));

        $this->assertSame('contestee', $vente->fresh()->fiabilite());
        $this->actingAs($this->vendeur)->post(route('account.reviews.store', $vente), ['rating' => 1])->assertForbidden();
    }

    public function test_seul_l_acheteur_designe_peut_repondre(): void
    {
        $vente = $this->vente(30, 'especes', $this->acheteur);
        $intrus = $this->membre('intrus@ex.com');

        $this->actingAs($this->vendeur)->patch(route('account.transactions.confirm-purchase', $vente))->assertForbidden();
        $this->actingAs($intrus)->patch(route('account.transactions.confirm-purchase', $vente))->assertForbidden();
        // Paiement en ligne : rien à confirmer.
        $enLigne = $this->vente(30, 'cb', $this->acheteur);
        $this->actingAs($this->acheteur)->patch(route('account.transactions.confirm-purchase', $enLigne))->assertForbidden();
    }

    // --- Tableau de bord ------------------------------------------------------

    public function test_le_tableau_de_bord_separe_l_argent_reel_des_declarations(): void
    {
        $this->vente(20.00, 'cb', $this->acheteur);
        $this->vente(30.00, 'especes');
        $this->vente(15.50, 'especes', $this->acheteur, ['acheteur_confirme_at' => now()]);
        $this->vente(50.00, 'especes', $this->acheteur, ['acheteur_conteste_at' => now()]);
        $this->vente(0, 'echange');

        $p = TableauDeBord::periode('30j');

        $this->assertSame(1, $p['ventes'], 'Seule la vente payée en ligne est certaine.');
        $this->assertEqualsWithDelta(20.00, $p['volume'], 0.001);
        $this->assertEqualsWithDelta(20.00, $p['panier_moyen'], 0.001);
        $this->assertEqualsWithDelta(2.90, $p['revenus'], 0.001);
        $this->assertSame(2, $p['ventes_declarees'], 'La vente contestée et l’échange ne comptent pas.');
        $this->assertEqualsWithDelta(45.50, $p['volume_declare'], 0.001);
        $this->assertSame(1, $p['ventes_declarees_confirmees']);
        $this->assertSame(1, array_sum(TableauDeBord::serieJournaliere(TableauDeBord::ventes(), 3)));
    }

    // --- Écrans -----------------------------------------------------------------

    public function test_la_fiche_admin_montre_les_noms_et_les_contacts(): void
    {
        $admin = $this->membre('cabinet@taochyconsulting.fr', 'Admin');
        $inconnu = $this->vente(30, 'especes');
        Message::create(['listing_id' => $inconnu->listing_id, 'sender_id' => $this->acheteur->id, 'receiver_id' => $this->vendeur->id, 'body' => 'Toujours dispo ?']);
        $connue = $this->vente(20, 'especes', $this->acheteur);

        $this->actingAs($admin)->get('/admin/transactions/' . $inconnu->id)
            ->assertOk()
            ->assertSee('Vendeuse Marie')
            ->assertSee('Déclarée, acheteur inconnu')
            ->assertSee('Qui était en contact avec le vendeur')
            ->assertSee('Acheteur Paul')
            ->assertDontSee('Toujours dispo ?');

        $this->actingAs($admin)->get('/admin/transactions/' . $connue->id)
            ->assertOk()
            ->assertSee('Acheteur Paul')
            ->assertSee('Déclarée, acheteur membre')
            ->assertDontSee('Qui était en contact avec le vendeur');

        $this->actingAs($admin)->get('/admin/transactions')->assertOk()->assertSee('Fiabilité');
    }

    public function test_le_vendeur_peut_retirer_sans_declarer_de_vente(): void
    {
        $annonce = $this->annonce();

        $this->actingAs($this->vendeur)->get(route('listings.show', $annonce))
            ->assertOk()
            ->assertSee('L’article est parti ? Dites-nous comment :')
            ->assertSee('Plus disponible : retirer l’annonce');

        // Le bouton « Vendue » du compte mène au choix, plus de vente fantôme.
        $this->actingAs($this->vendeur)->get(route('account.dashboard'))
            ->assertSee(route('listings.show', $annonce) . '#fin-annonce', false);
    }
}

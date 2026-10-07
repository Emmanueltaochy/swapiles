<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jamais de lien dans un lien.
 *
 * Le HTML l'interdit, et le navigateur « répare » en fermant le premier lien
 * avant le second. Sur les cartes d'annonce, le cœur favori des visiteurs non
 * connectés était un lien vers la connexion, placé dans le lien de la carte :
 * la carte était coupée en deux, la photo dans une case de la grille et le
 * texte dans la suivante. Une annonce par ligne, décalée — pour tous les
 * visiteurs, invisible pour un membre connecté.
 */
class NoNestedLinksTest extends TestCase
{
    use RefreshDatabase;

    private User $vendeur;

    private Listing $annonce;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendeur = User::create([
            'name' => 'Isabelle',
            'email' => 'isa' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
        $this->vendeur->forceFill(['email_verified_at' => now()])->save();

        foreach (['Lit à barreaux en bois', 'Lot 12 mois', 'Lot 2 salopettes'] as $titre) {
            $this->annonce = Listing::create([
                'user_id' => $this->vendeur->id,
                'title' => $titre,
                'description' => 'Description de test suffisamment longue.',
                'price' => 25,
                'currency' => 'EUR',
                'listing_type' => 'achat',
                'status' => 'published',
                'territoire' => 'La Réunion',
                'etat' => 'Bon état',
                'category_level1' => 'enfant',
                'category_level2' => 'vetements-enfants',
            ]);
        }
    }

    /**
     * Liens ouverts au moment où un nouveau lien commence. Les scripts et les
     * commentaires sont retirés : ils peuvent contenir « <a » sans être du HTML.
     *
     * @return list<string> extraits fautifs
     */
    private function liensImbriques(string $html): array
    {
        $html = preg_replace('#<script\b.*?</script>#is', '', $html);
        $html = preg_replace('#<!--.*?-->#s', '', $html);

        preg_match_all('#<a\b[^>]*>|</a\s*>#i', $html, $balises, PREG_OFFSET_CAPTURE);

        $ouverts = [];
        $fautes = [];

        foreach ($balises[0] as [$balise, $position]) {
            if (stripos($balise, '</a') === 0) {
                array_pop($ouverts);

                continue;
            }

            if ($ouverts) {
                $fautes[] = end($ouverts) . '  >>>  ' . $balise;
            }

            $ouverts[] = substr($balise, 0, 120);
        }

        return $fautes;
    }

    private function verifier(string $url, ?User $membre = null): void
    {
        $requete = $membre ? $this->actingAs($membre) : $this;
        $html = $requete->get($url)->assertOk()->getContent();

        $fautes = $this->liensImbriques($html);

        $this->assertSame([], $fautes, "Lien dans un lien sur {$url} (" . ($membre ? 'membre' : 'visiteur') . ") :\n" . implode("\n", $fautes));
    }

    public function test_aucun_lien_imbrique_pour_un_visiteur(): void
    {
        foreach ([
            '/',
            route('search'),
            route('search', ['category' => 'enfant']),
            route('listings.show', $this->annonce),
            route('profiles.show', $this->vendeur),
        ] as $url) {
            $this->verifier($url);
        }
    }

    public function test_aucun_lien_imbrique_pour_un_membre(): void
    {
        $membre = User::create([
            'name' => 'Marie',
            'email' => 'marie' . uniqid() . '@ex.com',
            'password' => bcrypt('secret1234'),
            'territoire' => 'La Réunion',
        ]);
        $membre->forceFill(['email_verified_at' => now()])->save();

        foreach ([
            '/',
            route('search'),
            route('listings.show', $this->annonce),
            route('profiles.show', $this->vendeur),
            route('account.dashboard'),
            route('account.favorites.index'),
        ] as $url) {
            $this->verifier($url, $membre);
        }
    }

    public function test_le_coeur_d_un_visiteur_est_un_bouton(): void
    {
        $html = $this->get(route('search'))->assertOk()->getContent();

        $this->assertStringContainsString('data-favori-connexion="' . route('login') . '"', $html);
        $this->assertDoesNotMatchRegularExpression('#<a[^>]+aria-label="Se connecter pour ajouter aux favoris"#', $html);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** « Comment ça marche » : page dédiée (sur l'accueil, la section était sous le défilement infini). */
class CommentCaMarcheTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_page_explique_vente_achat_remise_et_frais(): void
    {
        $this->get('/comment-ca-marche')->assertOk()
            ->assertSee('Comment ça marche ?')
            ->assertSee('Publiez gratuitement')
            ->assertSee('Payez en confiance')
            ->assertSee('Point relais · 3 €', false)
            ->assertSee('10 % du prix (0,50 € minimum, 15 € maximum)', false);
    }

    public function test_le_menu_pointe_vers_la_page(): void
    {
        $this->get('/')->assertOk()->assertSee(route('comment-ca-marche'), false);
    }
}

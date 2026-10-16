<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationsPastilleTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pastille_reste_jusqu_a_l_ouverture_des_notifications(): void
    {
        Queue::fake();
        $moi = User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        Notification::create(['user_id' => $moi->id, 'type' => 'favorite_added', 'title' => 'Nouveau favori ❤️', 'message' => 'Tom a ajouté « Robe » à ses favoris.']);

        // Mon compte : la notification reste non lue, avec la pastille sur le raccourci.
        $this->actingAs($moi)->get(route('account.dashboard'))->assertOk()
            ->assertSee('1 non lu(s)', false);
        $this->assertSame(1, Notification::whereNull('read_at')->count());

        // Page Notifications : elle s'affiche en « nouvelle », puis la pastille disparaît.
        $this->get(route('account.notifications.index'))->assertOk()
            ->assertSee('1 nouvelle')->assertSee('Nouveau favori');
        $this->assertSame(0, Notification::whereNull('read_at')->count());

        $this->get(route('account.dashboard'))->assertOk()->assertDontSee('non lu(s)', false);
    }
}

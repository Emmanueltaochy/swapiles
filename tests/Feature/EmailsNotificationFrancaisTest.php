<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Notifications\ListingFavoritedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailsNotificationFrancaisTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_signe_swapiles_et_en_francais(): void
    {
        config(['app.name' => 'Laravel']);
        $lea = User::create(['name' => 'Lea', 'email' => 'lea@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $tom = User::create(['name' => 'Tom', 'email' => 'tom@ex.com', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $tshirt = Listing::create(['user_id' => $lea->id, 'title' => 'T shirt', 'description' => 'Description de test assez longue.', 'price' => 5, 'status' => 'published', 'listing_type' => 'achat', 'territoire' => 'La Réunion']);

        $html = (string) (new ListingFavoritedNotification($tshirt, $tom))->toMail($lea)->render();

        $this->assertStringContainsString('L’équipe Swap’Îles', $html);
        $this->assertStringContainsString('ne s’ouvre pas ? Copiez ce lien', $html);
        $this->assertStringNotContainsString('Laravel', $html);
        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringNotContainsString('trouble clicking', $html);
    }
}

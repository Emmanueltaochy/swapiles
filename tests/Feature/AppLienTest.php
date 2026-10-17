<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AppLienTest extends TestCase
{
    use RefreshDatabase;

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Instagram 350.0';
    private const ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/128.0 Mobile Safari/537.36 Instagram 350.0';
    private const PC = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0 Safari/537.36';

    public function test_le_bon_store_selon_le_telephone(): void
    {
        $this->withHeader('User-Agent', self::IPHONE)->get('/app?src=insta')->assertRedirect(config('deeplinks.stores.ios'));

        $android = $this->withHeader('User-Agent', self::ANDROID)->get('/installer?src=insta');
        $android->assertRedirect();
        $this->assertStringStartsWith('https://play.google.com/store/apps/details?id=', $android->headers->get('Location'));
        $this->assertStringContainsString(rawurlencode('utm_source=insta&utm_medium=lien_app'), $android->headers->get('Location'));

        $this->withHeader('User-Agent', self::PC)->get('/app')->assertOk()->assertSee('App Store (iPhone)')->assertSee('Google Play (Android)');

        $this->assertSame(['android' => 1, 'ios' => 1, 'web' => 1], DB::table('app_lien_clics')->orderBy('plateforme')->pluck('plateforme')->countBy()->all());
        $this->assertSame(2, DB::table('app_lien_clics')->where('source', 'insta')->count());
    }

    public function test_admin_voit_les_clics(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'cabinet@taochyconsulting.fr', 'password' => bcrypt('secret1234'), 'territoire' => 'La Réunion']);
        $this->withHeader('User-Agent', self::IPHONE)->get('/app?src=insta');

        $this->actingAs($admin)->get('/admin/app-installations')->assertOk()
            ->assertSee('Lien unique swapiles.com/app')->assertSee('insta');
    }
}

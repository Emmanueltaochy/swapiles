<?php

namespace App\Http\Controllers;

use App\Support\BotDetector;
use App\Support\ParrainageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Lien unique « swapiles.com/app » : iPhone → App Store, Android → Google
 * Play, ordinateur → page avec les deux boutons. Un seul lien à mettre en
 * bio Instagram, sur une affiche ou dans un message.
 * « ?src=insta » (facultatif) permet de savoir d'où viennent les clics.
 */
class AppLienController extends Controller
{
    public function __invoke(Request $request)
    {
        $plateforme = ParrainageService::plateforme($request->userAgent());
        $source = Str::of((string) $request->query('src', ''))->lower()->replaceMatches('/[^a-z0-9_-]/', '')->limit(40, '')->value() ?: null;

        if (! BotDetector::isBot($request->userAgent()) && Schema::hasTable('app_lien_clics')) {
            DB::table('app_lien_clics')->insert(['plateforme' => $plateforme, 'source' => $source, 'created_at' => now()]);
        }

        $utm = ['source' => $source ?: 'lien_app', 'medium' => 'lien_app', 'campaign' => null];
        $android = \App\Support\LiensStores::android($utm);
        $ios = \App\Support\LiensStores::ios($utm);

        return match ($plateforme) {
            'ios' => redirect()->away($ios),
            'android' => redirect()->away($android),
            default => response()->view('app-lien', ['ios' => $ios, 'android' => $android]),
        };
    }
}

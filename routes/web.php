<?php

use App\Http\Controllers\Account\TransactionDetailController;

use App\Http\Controllers\Account\TransactionController;

use App\Http\Controllers\ListingOfferController;

use App\Http\Controllers\Auth\PasswordResetController;

use App\Http\Controllers\SearchSuggestionController;

use App\Http\Controllers\PublicActivityController;

use App\Http\Controllers\Stripe\StripeConnectController;

use App\Http\Controllers\Transaction\TransactionWorkflowController;

use App\Http\Controllers\Checkout\CheckoutController;

use App\Http\Controllers\Stripe\StripeWebhookController;

use App\Http\Controllers\ProfileController;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Account\ListingManageController;
use App\Http\Controllers\Account\MessageController;
use App\Http\Controllers\Account\FavoriteController;
use App\Http\Controllers\Account\WalletController;
use App\Http\Controllers\Account\ProfileSettingsController;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/territoire/{territoire}', function (string $territoire) {
    $territoires = [
        'reunion' => 'La Réunion',
        'guyane' => 'Guyane',
        'martinique' => 'Martinique',
        'guadeloupe' => 'Guadeloupe',
        'mayotte' => 'Mayotte',
    ];

    abort_unless(isset($territoires[$territoire]), 404);

    return redirect()
        ->route('home')
        ->withCookie(cookie('swapiles_territoire', $territoires[$territoire], 60 * 24 * 365));
})->name('territoire.switch');
Route::get('/recherche', [HomeController::class, 'search'])->name('search');

// Pages de destination SEO : catalogue par territoire et par catégorie
Route::get('/iles/{territoire}', [\App\Http\Controllers\CatalogController::class, 'territoire'])->name('catalog.territoire');
Route::get('/iles/{territoire}/{categorie}', [\App\Http\Controllers\CatalogController::class, 'category'])->name('catalog.category');

// Pages légales
Route::get('/mentions-legales', [\App\Http\Controllers\LegalController::class, 'mentions'])->name('legal.mentions');
Route::get('/cgu', [\App\Http\Controllers\LegalController::class, 'cgu'])->name('legal.cgu');
Route::get('/cgv', [\App\Http\Controllers\LegalController::class, 'cgv'])->name('legal.cgv');
Route::get('/confidentialite', [\App\Http\Controllers\LegalController::class, 'confidentialite'])->name('legal.privacy');

Route::get('/annonce/{listing}', [ListingController::class, 'show'])->name('listings.show');


Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [AuthController::class, 'login'])->middleware('throttle:8,1')->name('login.store');

    Route::get('/inscription', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/inscription', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});

// Confirmation d'adresse e-mail (lien signé, accessible même déconnecté).
Route::get('/email/verifier/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

Route::middleware('auth')->group(function () {
    Route::get('/mon-compte', [AccountController::class, 'dashboard'])->name('account.dashboard');
    Route::get('/mon-wallet', [WalletController::class, 'index'])->name('account.wallet.index');
    Route::get('/mon-profil/modifier', [ProfileSettingsController::class, 'edit'])->name('account.profile.edit');
    Route::put('/mon-profil/modifier', [ProfileSettingsController::class, 'update'])->name('account.profile.update');

    Route::get('/deposer-une-annonce', [ListingManageController::class, 'create'])->name('account.listings.create');
    Route::post('/deposer-une-annonce', [ListingManageController::class, 'store'])->name('account.listings.store');

    Route::get('/mes-annonces/{listing}/modifier', [ListingManageController::class, 'edit'])->name('account.listings.edit');
    Route::put('/mes-annonces/{listing}', [ListingManageController::class, 'update'])->name('account.listings.update');
    Route::patch('/mes-annonces/{listing}/masquer', [ListingManageController::class, 'hide'])->name('account.listings.hide');
    Route::patch('/mes-annonces/{listing}/vendue', [ListingManageController::class, 'markSold'])->name('account.listings.sold');
    Route::patch('/mes-annonces/{listing}/publier', [ListingManageController::class, 'publish'])->name('account.listings.publish');
    Route::patch('/mes-annonces/{listing}/photos/{image}/principale', [ListingManageController::class, 'makeMainImage'])->name('account.listings.images.main');
    Route::delete('/mes-annonces/{listing}/photos/{image}', [ListingManageController::class, 'destroyImage'])->name('account.listings.images.destroy');
    Route::delete('/mes-annonces/{listing}', [ListingManageController::class, 'destroy'])->name('account.listings.destroy');


    
    Route::get('/messages', [MessageController::class, 'index'])->name('account.messages.index');
    Route::get('/messages/annonce/{listing}/start', [MessageController::class, 'start'])->name('account.messages.start');
    Route::get('/messages/avec/{user}', [MessageController::class, 'showGeneral'])->name('account.messages.show.general');
    Route::post('/messages/avec/{user}', [MessageController::class, 'storeGeneral'])->name('account.messages.store.general');
    Route::get('/messages/annonce/{listing}/avec/{user}', [MessageController::class, 'show'])->name('account.messages.show');
    Route::post('/messages/annonce/{listing}/avec/{user}', [MessageController::class, 'store'])->name('account.messages.store');

    
    Route::post('/vendeurs/{user}/suivre', [\App\Http\Controllers\Account\SellerFollowController::class, 'toggle'])->name('account.seller-follow.toggle');
    Route::get('/vendeurs-suivis', [\App\Http\Controllers\Account\FollowedSellerController::class, 'index'])->name('account.followed-sellers.index');
    Route::get('/mes-abonnes', [\App\Http\Controllers\Account\FollowerController::class, 'index'])->name('account.followers.index');
    Route::get('/favoris', [FavoriteController::class, 'index'])->name('account.favorites.index');
    Route::post('/favoris/{listing}/toggle', [FavoriteController::class, 'toggle'])->name('account.favorites.toggle');
    Route::get('/favoris/{listing}/toggle', [FavoriteController::class, 'toggle'])->name('account.favorites.toggle.get');

    Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout');

    Route::post('/email/renvoyer-confirmation', [AuthController::class, 'resendVerification'])
        ->name('verification.send');
});


Route::middleware('guest')->group(function () {
    Route::get('/magic-link', [MagicLinkController::class, 'show'])->name('magic.login');
    Route::post('/magic-link', [MagicLinkController::class, 'send'])->name('magic.login.send');
    Route::get('/magic-link/{token}', [MagicLinkController::class, 'verify'])->name('magic.login.verify');
});

Route::get('/profil/{user}', [ProfileController::class, 'show'])->name('profiles.show');

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');


Route::middleware('auth')->group(function () {
    Route::get('/checkout/{listing}', [CheckoutController::class, 'start'])->name('checkout.show');
    Route::post('/checkout/{listing}', [CheckoutController::class, 'start'])->name('checkout.start');
    Route::get('/checkout/success/{transaction}', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/checkout/cancel/{transaction}', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
});



Route::middleware('auth')->group(function () {

    Route::patch('/transactions/{transaction}/expediee', [TransactionWorkflowController::class, 'shipped'])
        ->name('transactions.shipped');

    Route::patch('/transactions/{transaction}/recue', [TransactionWorkflowController::class, 'received'])
        ->name('transactions.received');
});


Route::middleware('auth')->group(function () {

    Route::get('/stripe/connect', [StripeConnectController::class, 'onboarding'])
        ->name('stripe.connect.onboarding');

    Route::get('/stripe/connect/refresh', [StripeConnectController::class, 'refresh'])
        ->name('stripe.connect.refresh');

    Route::get('/stripe/connect/return', [StripeConnectController::class, 'returned'])
        ->name('stripe.connect.return');

    // Activation du portefeuille façon Vinted : onboarding Stripe intégré (embedded)
    Route::get('/portefeuille/activer', [StripeConnectController::class, 'activate'])
        ->name('stripe.connect.activate');
    Route::post('/portefeuille/session', [StripeConnectController::class, 'accountSession'])
        ->name('stripe.connect.account-session');
    Route::get('/portefeuille/active', [StripeConnectController::class, 'activated'])
        ->name('stripe.connect.activated');
});

Route::get('/activity/recent', [PublicActivityController::class, 'recent'])->name('activity.recent');

Route::get('/search-suggestions', SearchSuggestionController::class)->name('search.suggestions');


Route::middleware('guest')->group(function () {
    Route::get('/mot-de-passe-oublie', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/mot-de-passe/email', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/mot-de-passe/reinitialiser/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/mot-de-passe/reset', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/annonce/{listing}/offre', [ListingOfferController::class, 'store'])->middleware('auth')->name('offers.store');

Route::post('/offres/{offer}/accepter', [ListingOfferController::class, 'accept'])->middleware('auth')->name('offers.accept');
Route::post('/offres/{offer}/refuser', [ListingOfferController::class, 'refuse'])->middleware('auth')->name('offers.refuse');

Route::get('/mon-compte/transactions', [TransactionController::class, 'index'])->middleware('auth')->name('account.transactions.index');

Route::get('/mon-compte/transactions/{transaction}', [TransactionDetailController::class, 'show'])->middleware('auth')->name('account.transactions.show');


Route::get('/recherche/live', function (\Illuminate\Http\Request $request) {
    $q = trim($request->get('q', ''));

    if (strlen($q) < 2) {
        return response('');
    }

    $listings = \App\Models\Listing::with(['images', 'user'])
        ->where('status', 'published')
        ->where(function ($query) use ($q) {
            $query->where('title', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhere('marque', 'like', "%{$q}%")
                ->orWhere('category_level1', 'like', "%{$q}%");
        })
        ->latest()
        ->take(6)
        ->get();

    return view('partials.live-search-results', compact('listings'));
})->name('search.live');


Route::post('/annonce/{listing}/contre-offre/{user}', [ListingOfferController::class, 'counter'])->middleware('auth')->name('offers.counter');



Route::get('/favoris/alertes/{alert}/read', function (\App\Models\FavoriteAlert $alert) {

    abort_unless(auth()->check() && $alert->user_id === auth()->id(), 403);

    $alert->update([
        'read_at' => now(),
    ]);

    if ($alert->listing) {
        return redirect()->route('listings.show', $alert->listing);
    }

    return redirect()->route('account.favorites.index');

})->middleware('auth')->name('favorite-alerts.read');

use App\Http\Controllers\Account\ColissimoController;

Route::middleware(['auth'])->group(function () {
    Route::post('/transactions/{transaction}/colissimo/generer', [ColissimoController::class, 'generate'])->name('colissimo.generate');
    Route::get('/transactions/{transaction}/colissimo/etiquette', [ColissimoController::class, 'download'])->name('colissimo.download');
});

Route::get('/api/colissimo/points-relais', [\App\Http\Controllers\ColissimoPickupController::class, 'search'])
    ->middleware('auth')
    ->name('colissimo.pickup.search');

Route::middleware(['auth'])->group(function () {
    Route::post('/mon-compte/ventes/{transaction}/colissimo/generer', [\App\Http\Controllers\Account\ColissimoLabelController::class, 'generate'])->name('account.colissimo.generate');
    Route::get('/mon-compte/ventes/{transaction}/colissimo/telecharger', [\App\Http\Controllers\Account\ColissimoLabelController::class, 'download'])->name('account.colissimo.download');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/mon-compte/adresses', [\App\Http\Controllers\Account\AddressController::class, 'edit'])->name('account.addresses.edit');
    Route::post('/mon-compte/adresses', [\App\Http\Controllers\Account\AddressController::class, 'update'])->name('account.addresses.update');
});


Route::middleware('auth')->group(function () {
    Route::get('/notifications', [\App\Http\Controllers\Account\NotificationController::class, 'index'])->name('account.notifications.index');
    Route::post('/notifications/read', [\App\Http\Controllers\Account\NotificationController::class, 'markAllAsRead'])->name('account.notifications.read');
});

Route::get('/robots.txt', function () {
    return response(
"User-agent: *
Allow: /

Sitemap: " . url('/sitemap.xml') . "
", 200)->header('Content-Type', 'text/plain');
})->name('robots');

Route::get('/sitemap.xml', function () {
    $urls = collect([
        ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'],
        ['loc' => route('search'), 'priority' => '0.9', 'changefreq' => 'daily'],
    ]);

    // Pages légales (confiance / E-E-A-T)
    foreach (['legal.mentions', 'legal.cgu', 'legal.cgv', 'legal.privacy'] as $legalRoute) {
        $urls->push(['loc' => route($legalRoute), 'priority' => '0.3', 'changefreq' => 'yearly']);
    }

    // Pages de destination par territoire + catégorie (fort levier SEO)
    $territoiresMap = [
        'la-reunion' => 'La Réunion',
        'martinique' => 'Martinique',
        'guadeloupe' => 'Guadeloupe',
        'guyane' => 'Guyane',
        'mayotte' => 'Mayotte',
    ];
    foreach ($territoiresMap as $slug => $label) {
        $urls->push(['loc' => route('catalog.territoire', $slug), 'priority' => '0.9', 'changefreq' => 'daily']);

        $cats = \App\Models\Listing::where('status', 'published')
            ->where('territoire', $label)
            ->whereNotNull('category_level1')
            ->where('category_level1', '!=', '')
            ->distinct()
            ->pluck('category_level1');

        foreach ($cats as $cat) {
            $urls->push([
                'loc' => route('catalog.category', [$slug, \Illuminate\Support\Str::slug($cat)]),
                'priority' => '0.7',
                'changefreq' => 'weekly',
            ]);
        }
    }

    // Annonces publiées
    $listings = \App\Models\Listing::where('status', 'published')
        ->latest('updated_at')
        ->limit(5000)
        ->get(['id', 'updated_at']);

    foreach ($listings as $listing) {
        $urls->push([
            'loc' => route('listings.show', $listing),
            'lastmod' => optional($listing->updated_at)->toAtomString(),
            'priority' => '0.8',
            'changefreq' => 'weekly',
        ]);
    }

    // Profils vendeurs ayant au moins une annonce publiée
    $sellerIds = \App\Models\Listing::where('status', 'published')
        ->distinct()
        ->limit(2000)
        ->pluck('user_id')
        ->filter();

    if ($sellerIds->isNotEmpty()) {
        $sellers = \App\Models\User::whereIn('id', $sellerIds)
            ->get(['id', 'updated_at']);

        foreach ($sellers as $seller) {
            $urls->push([
                'loc' => route('profiles.show', $seller),
                'lastmod' => optional($seller->updated_at)->toAtomString(),
                'priority' => '0.5',
                'changefreq' => 'weekly',
            ]);
        }
    }

    $xml = view('sitemap', compact('urls'))->render();

    return response($xml, 200)->header('Content-Type', 'application/xml');
})->name('sitemap');


Route::post('/annonce/{listing}/demande/{mode}', [\App\Http\Controllers\ListingController::class, 'requestMode'])
    ->middleware('auth')
    ->name('listings.request-mode');

// Système d'échange
Route::get('/annonce/{listing}/proposer-echange', [\App\Http\Controllers\ExchangeController::class, 'create'])
    ->name('exchange.create');
Route::post('/annonce/{listing}/proposer-echange', [\App\Http\Controllers\ExchangeController::class, 'store'])
    ->middleware('auth')
    ->name('exchange.store');
Route::post('/echanges/{proposal}/accepter', [\App\Http\Controllers\ExchangeController::class, 'accept'])
    ->middleware('auth')
    ->name('exchange.accept');
Route::post('/echanges/{proposal}/refuser', [\App\Http\Controllers\ExchangeController::class, 'refuse'])
    ->middleware('auth')
    ->name('exchange.refuse');

Route::patch('/mes-annonces/{listing}/paiement-especes-recu', [\App\Http\Controllers\Account\ListingManageController::class, 'markCashPaid'])
    ->middleware('auth')
    ->name('account.listings.cash-paid');

Route::patch('/mes-annonces/{listing}/echange-effectue', [\App\Http\Controllers\Account\ListingManageController::class, 'markExchanged'])
    ->middleware('auth')
    ->name('account.listings.exchanged');

Route::patch('/mes-annonces/{listing}/don-remis', [\App\Http\Controllers\Account\ListingManageController::class, 'markGiven'])
    ->middleware('auth')
    ->name('account.listings.given');



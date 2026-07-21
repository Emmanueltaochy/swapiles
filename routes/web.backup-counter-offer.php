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
Route::get('/recherche', [HomeController::class, 'search'])->name('search');
Route::get('/annonce/{listing}', [ListingController::class, 'show'])->name('listings.show');


Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/connexion', [AuthController::class, 'login'])->name('login.store');

    Route::get('/inscription', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/inscription', [AuthController::class, 'register'])->name('register.store');
});

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
    Route::get('/messages/annonce/{listing}/avec/{user}', [MessageController::class, 'show'])->name('account.messages.show');
    Route::post('/messages/annonce/{listing}/avec/{user}', [MessageController::class, 'store'])->name('account.messages.store');

    
    Route::get('/favoris', [FavoriteController::class, 'index'])->name('account.favorites.index');
    Route::post('/favoris/{listing}/toggle', [FavoriteController::class, 'toggle'])->name('account.favorites.toggle');
    Route::get('/favoris/{listing}/toggle', [FavoriteController::class, 'toggle'])->name('account.favorites.toggle.get');

    Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout');
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
});

Route::get('/activity/recent', [PublicActivityController::class, 'recent'])->name('activity.recent');

Route::get('/search-suggestions', SearchSuggestionController::class)->name('search.suggestions');


Route::middleware('guest')->group(function () {
    Route::get('/mot-de-passe-oublie', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/mot-de-passe/email', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/mot-de-passe/reset/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
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


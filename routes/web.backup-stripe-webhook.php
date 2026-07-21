<?php

use App\Http\Controllers\ProfileController;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Account\ListingManageController;
use App\Http\Controllers\Account\MessageController;
use App\Http\Controllers\Account\FavoriteController;
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

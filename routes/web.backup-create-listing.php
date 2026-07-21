<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\AccountController;

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
    Route::post('/deconnexion', [AuthController::class, 'logout'])->name('logout');
});


Route::middleware('guest')->group(function () {
    Route::get('/magic-link', [MagicLinkController::class, 'show'])->name('magic.login');
    Route::post('/magic-link', [MagicLinkController::class, 'send'])->name('magic.login.send');
    Route::get('/magic-link/{token}', [MagicLinkController::class, 'verify'])->name('magic.login.verify');
});


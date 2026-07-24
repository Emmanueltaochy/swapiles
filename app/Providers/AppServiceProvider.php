<?php

namespace App\Providers;

use App\Listeners\LogSentEmail;
use App\Models\Transaction;
use App\Observers\TransactionObserver;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Transaction::observe(TransactionObserver::class);

        // Journalise chaque e-mail envoyé (pour l'onglet Admin > Activité > Emails).
        Event::listen(MessageSent::class, LogSentEmail::class);
    }
}

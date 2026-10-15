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

        // Admin : « Rien pour l'instant » plutôt que « Aucun(e) signalements ».
        \Filament\Tables\Table::configureUsing(fn (\Filament\Tables\Table $table) => $table
            ->emptyStateHeading('Rien pour l’instant')
            ->emptyStateDescription('Les lignes apparaîtront ici dès qu’il y aura des données.'));

        // Journalise chaque e-mail envoyé (pour l'onglet Admin > Activité > Emails).
        Event::listen(MessageSent::class, LogSentEmail::class);

        // Navigation sans rechargement (Turbo) : ces fichiers changent de nom à
        // chaque mise en ligne. Turbo le remarque et recharge alors la page en
        // entier, pour que personne ne reste sur l'ancienne version du site.
        \Illuminate\Support\Facades\Vite::useScriptTagAttributes(['data-turbo-track' => 'reload']);
        \Illuminate\Support\Facades\Vite::useStyleTagAttributes(['data-turbo-track' => 'reload']);
    }
}

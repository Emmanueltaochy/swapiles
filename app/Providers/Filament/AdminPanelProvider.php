<?php

namespace App\Providers\Filament;

use App\Http\Middleware\FilamentAdminAuthenticate;
use Filament\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\EnsureAdminEmail;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Navigation\NavigationGroup;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName("Swap'Îles Admin")
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('images/app-icon.png'))
            ->colors([
                'primary' => Color::Teal,
            ])
            // Menu rangé par métier, du pilotage quotidien à la technique.
            // Chaque page déclare son groupe (navigationGroup) ; l'ordre des
            // groupes est fixé ici. Les groupes se replient d'un clic.
            ->navigationGroups([
                NavigationGroup::make('Audience'),
                NavigationGroup::make('Ventes'),
                NavigationGroup::make('Catalogue'),
                NavigationGroup::make('Membres'),
                NavigationGroup::make('Modération'),
                NavigationGroup::make('Marketing'),
                NavigationGroup::make('Points relais'),
                NavigationGroup::make('Système')->collapsed(),
            ])
            ->collapsibleNavigationGroups()
            ->sidebarCollapsibleOnDesktop()
            // Même correctif que sur le site : sur iPhone/iPad, WebKit zoome la
            // page dès qu'on touche un champ dont la police fait moins de 16 px,
            // et la page reste ensuite zoomée. Filament utilise du 14 px.
            ->renderHook(
                \Filament\View\PanelsRenderHook::HEAD_END,
                fn (): string => '<style>@media (pointer: coarse){'
                    . "input:not([type='checkbox']):not([type='radio']):not([type='range']):not([type='color']):not([type='file']),"
                    . 'select,textarea{font-size:16px !important;}}</style>'
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Aussi pour les actualisations Livewire (widgets, tableaux).
            ->middleware([\App\Http\Middleware\AdminEnFrancais::class], isPersistent: true)
            ->authMiddleware([
                // Notre authentification : un membre non administrateur est
                // redirigé vers le site au lieu de recevoir un 403 sans issue.
                FilamentAdminAuthenticate::class,
                EnsureAdminEmail::class,
            ]);
    }
}

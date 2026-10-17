<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Tableau\InstallationsChart;
use App\Support\InstallationsAppli;
use Filament\Pages\Page;

/** Installations de l'appli iPhone / Android réellement utilisées. */
class AppInstallations extends Page
{
    protected static ?string $navigationLabel = 'Applications mobiles';
    protected static ?string $title = 'Applications mobiles';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';
    protected static ?int $navigationSort = 6;

    protected static string | \UnitEnum | null $navigationGroup = 'Audience';

    protected string $view = 'filament.pages.app-installations';

    public function getViewData(): array
    {
        return [
            'chiffres' => InstallationsAppli::chiffres(),
            'membres' => InstallationsAppli::membresDansLAppli(),
            'debut' => InstallationsAppli::debutComptage(),
            'clics' => InstallationsAppli::clicsLien(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [InstallationsChart::class];
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 1;
    }
}

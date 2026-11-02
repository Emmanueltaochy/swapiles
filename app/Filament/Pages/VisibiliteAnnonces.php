<?php

namespace App\Filament\Pages;

use App\Support\Remontees;
use Filament\Pages\Page;

/**
 * Visibilité des annonces : combien de temps une nouvelle annonce reste en
 * première page, et ce que donnent les remontées et les conseils de prix.
 * Sert à savoir quand un « Booster » payant aura du sens.
 */
class VisibiliteAnnonces extends Page
{
    protected static ?string $navigationLabel = 'Visibilité des annonces';

    protected static ?string $title = 'Visibilité des annonces';

    protected static ?string $slug = 'visibilite-annonces';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-trending-up';

    protected static string | \UnitEnum | null $navigationGroup = 'Valeur & marché';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.visibilite-annonces';

    public function getSubheading(): ?string
    {
        return 'Remontées automatiques (baisse de prix, rotation des anciennes annonces) et conseils de prix aux vendeurs, sur 30 jours.';
    }

    public function getViewData(): array
    {
        return [
            'pages' => Remontees::tempsPremierePage(),
            'bilan' => Remontees::bilan(),
        ];
    }
}

<?php

namespace App\Filament\Pages;

use App\Support\ValeurEntreprise;
use Filament\Pages\Page;

/** Valeur estimée de Swap'Îles, d'après les vrais chiffres et des hypothèses prudentes. */
class ValeurSwapiles extends Page
{
    protected static ?string $navigationLabel = 'Valeur de Swap’Îles';

    protected static ?string $title = 'Valeur de Swap’Îles';

    protected static ?string $slug = 'valeur-swapiles';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string | \UnitEnum | null $navigationGroup = 'Valeur & marché';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.valeur-swapiles';

    public function getSubheading(): ?string
    {
        return 'Estimation prudente, calculée chaque jour sur les vrais chiffres. Ce n’est pas un prix garanti : seule une offre réelle fixe un prix.';
    }

    public function getViewData(): array
    {
        return ['v' => ValeurEntreprise::estimation()];
    }
}

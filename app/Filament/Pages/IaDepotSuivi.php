<?php

namespace App\Filament\Pages;

use App\Support\IaDepot;
use Filament\Pages\Page;

/** Suivi de l'IA dépôt : utilisation et coût du mois, garde-fous, détail de chaque utilisation. */
class IaDepotSuivi extends Page
{
    protected static ?string $navigationLabel = 'IA dépôt';

    protected static ?string $title = 'IA dépôt (remplissage des annonces)';

    protected static ?string $slug = 'ia-depot';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string | \UnitEnum | null $navigationGroup = 'Catalogue';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.ia-depot';

    public function getViewData(): array
    {
        return ['b' => IaDepot::bilan(), 'journal' => IaDepot::journal()];
    }
}

<?php

namespace App\Filament\Widgets\Membres;

use App\Support\MembresIndicateurs;
use Filament\Widgets\Widget;

/** En tête de la page Membres : répartition par île et indicateurs clés. */
class IndicateursMembres extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.membres.indicateurs';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return ['c' => MembresIndicateurs::chiffres()];
    }
}

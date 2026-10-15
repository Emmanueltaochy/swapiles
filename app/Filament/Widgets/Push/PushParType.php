<?php

namespace App\Filament\Widgets\Push;

use App\Support\PushSuivi;
use Filament\Widgets\Widget;

/** Quels types de notifications sont ouverts (30 derniers jours). */
class PushParType extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.push.par-type';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return ['lignes' => PushSuivi::parType(now()->subDays(30), now())];
    }
}

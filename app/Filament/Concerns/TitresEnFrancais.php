<?php

namespace App\Filament\Concerns;

/**
 * Titres à la française : « Points relais » et non « Points Relais »
 * (Filament met une majuscule à chaque mot, à l'anglaise).
 */
trait TitresEnFrancais
{
    public static function getTitleCaseModelLabel(): string
    {
        return \Illuminate\Support\Str::ucfirst(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return \Illuminate\Support\Str::ucfirst(static::getPluralModelLabel());
    }
}

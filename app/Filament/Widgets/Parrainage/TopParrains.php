<?php

namespace App\Filament\Widgets\Parrainage;

use App\Models\Parrainage;
use Filament\Widgets\Widget;

class TopParrains extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.parrainage.top-parrains';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        return [
            'top' => Parrainage::query()
                ->selectRaw("parrain_id, COUNT(*) as filleuls, SUM(CASE WHEN statut = 'valide' THEN 1 ELSE 0 END) as valides, SUM(CASE WHEN statut = 'valide' THEN montant_centimes ELSE 0 END) as gagne")
                ->groupBy('parrain_id')->orderByDesc('valides')->orderByDesc('filleuls')->limit(10)
                ->with('parrain:id,name,email,ambassadeur_at,a_mettre_en_avant_at')->get(),
        ];
    }
}

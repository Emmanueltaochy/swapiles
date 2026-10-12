<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Tableau;
use App\Support\TableauDeBord;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

/**
 * Tableau de bord : d'un coup d'œil, la journée, ce qui attend une action,
 * puis l'activité sur la période choisie.
 *
 * Les chiffres viennent tous de App\Support\TableauDeBord (testés) ; les
 * widgets ne font que les afficher.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static ?string $title = 'Tableau de bord';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 1;

    public function getSubheading(): ?string
    {
        return "Swap'Îles · " . ucfirst(now()->locale('fr')->translatedFormat('l j F Y'));
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('periode')
                ->label('Période des statistiques')
                ->options(TableauDeBord::PERIODES)
                ->default('30j')
                ->selectablePlaceholder(false)
                ->native(false),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            Tableau\AujourdhuiStats::class,
            Tableau\ATraiterStats::class,
            Tableau\PeriodeStats::class,
            Tableau\EvolutionChart::class,
            Tableau\VisiteursChart::class,
            Tableau\AnnoncesParIleChart::class,
            Tableau\DernieresVentes::class,
            Tableau\NouveauxMembres::class,
            Tableau\TopAnnonces::class,
        ];
    }

    public function getColumns(): int | array
    {
        return ['default' => 1, 'lg' => 2];
    }
}

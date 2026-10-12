<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Tableau;
use App\Support\TableauDeBord;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
                ->hiddenLabel()
                ->options(TableauDeBord::PERIODES)
                ->default('30j')
                ->selectablePlaceholder(false)
                ->native(false),
        ]);
    }

    /**
     * Ordre de la page : ce qui se passe maintenant (ne dépend d'aucun
     * filtre), PUIS le choix de la période, juste au-dessus de tout ce qu'il
     * change. Avant, le filtre trônait en haut alors que « Aujourd'hui » et
     * « À traiter » l'ignoraient.
     */
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make($this->getColumns())
                ->schema(fn (): array => $this->getWidgetsSchemaComponents(self::WIDGETS_MAINTENANT)),

            Section::make('Statistiques sur une période')
                ->description('Ce choix s’applique à tout ce qui suit : chiffres, évolution et visiteurs.')
                ->icon('heroicon-o-calendar-days')
                ->compact()
                ->schema([EmbeddedSchema::make('filtersForm')]),

            Grid::make($this->getColumns())
                ->schema(fn (): array => $this->getWidgetsSchemaComponents(self::WIDGETS_PERIODE)),
        ]);
    }

    /** Indépendants de la période. */
    public const WIDGETS_MAINTENANT = [
        Tableau\AujourdhuiStats::class,
        Tableau\ATraiterStats::class,
        Tableau\DernieresVentes::class,
        Tableau\NouveauxMembres::class,
        Tableau\AnnoncesParIleChart::class,
        Tableau\TopAnnonces::class,
    ];

    /** Filtrés par la période choisie. */
    public const WIDGETS_PERIODE = [
        Tableau\PeriodeStats::class,
        Tableau\EvolutionChart::class,
        Tableau\VisiteursChart::class,
    ];

    public function getWidgets(): array
    {
        return [...self::WIDGETS_MAINTENANT, ...self::WIDGETS_PERIODE];
    }

    public function getColumns(): int | array
    {
        return ['default' => 1, 'lg' => 2];
    }
}

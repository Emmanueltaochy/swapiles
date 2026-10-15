<?php

namespace App\Filament\Pages;

use App\Support\CalendrierStats;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/** Calendrier : un mois d'un coup d'œil, et le détail d'un jour au clic. */
class Calendrier extends Page
{
    protected static ?string $navigationLabel = 'Calendrier';

    protected static ?string $title = 'Calendrier';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string | \UnitEnum | null $navigationGroup = 'Audience';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.calendrier';

    public string $mois = '';

    public string $jour = '';

    public function mount(): void
    {
        $this->mois = now()->format('Y-m');
        $this->jour = now()->toDateString();
    }

    public function getSubheading(): ?string
    {
        return 'Touchez un jour pour voir ce qui s’est passé sur Swap’Îles ce jour-là.';
    }

    public function moisPrecedent(): void
    {
        $this->mois = Carbon::createFromFormat('Y-m-d', $this->mois . '-01')->subMonth()->format('Y-m');
    }

    public function moisSuivant(): void
    {
        $this->mois = Carbon::createFromFormat('Y-m-d', $this->mois . '-01')->addMonth()->format('Y-m');
    }

    public function choisir(string $jour): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour)) {
            $this->jour = $jour;
        }
    }

    public function getViewData(): array
    {
        $mois = Carbon::createFromFormat('Y-m-d', $this->mois . '-01')->startOfDay();
        $jours = CalendrierStats::mois($mois);

        return [
            'debutMois' => $mois,
            'jours' => $jours,
            'maxVisiteurs' => max(1, (int) collect($jours)->max('visiteurs')),
            'detail' => CalendrierStats::jour(Carbon::parse($this->jour)),
        ];
    }
}

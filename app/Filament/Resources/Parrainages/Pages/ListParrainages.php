<?php

namespace App\Filament\Resources\Parrainages\Pages;

use App\Filament\Resources\Parrainages\ParrainageResource;
use App\Filament\Widgets\Parrainage\ParrainageStats;
use App\Filament\Widgets\Parrainage\TopParrains;
use App\Models\Parrainage;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListParrainages extends ListRecords
{
    protected static string $resource = ParrainageResource::class;

    protected static ?string $title = 'Parrainages';

    public function getSubheading(): ?string
    {
        return (config('referral.enabled') ? 'Programme ouvert à tous.' : '🧪 Programme en test (REFERRAL_ENABLED désactivé) : réservé aux admins et testeurs.')
            . ' Récompense actuelle : ' . number_format((float) config('referral.reward_amount'), 2, ',', ' ') . ' €, crédit valable '
            . config('referral.credit_validity_days') . ' jours, ' . config('referral.max_rewards_per_month') . ' récompenses max par mois et par parrain.';
    }

    protected function getHeaderWidgets(): array
    {
        return [ParrainageStats::class, TopParrains::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')
                ->label('Exporter en CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => response()->streamDownload(function () {
                    $sortie = fopen('php://output', 'w');
                    fwrite($sortie, "\xEF\xBB\xBF");
                    fputcsv($sortie, ['Inscription', 'Parrain', 'E-mail parrain', 'Code', 'Filleul', 'E-mail filleul', 'Via', 'Statut', 'Motif refus', 'Récompense (€)', 'Commande', 'Validé le'], ';');
                    Parrainage::with(['parrain:id,name,email', 'filleul:id,name,email'])->orderBy('id')->chunk(500, function ($lignes) use ($sortie) {
                        foreach ($lignes as $p) {
                            fputcsv($sortie, [
                                $p->created_at?->format('d/m/Y'), $p->parrain?->name, $p->parrain?->email, $p->code,
                                $p->filleul?->name, $p->filleul?->email, $p->source,
                                Parrainage::STATUTS[$p->statut] ?? $p->statut,
                                $p->motif_refus ? (Parrainage::MOTIFS[$p->motif_refus] ?? $p->motif_refus) : '',
                                number_format($p->montant_centimes / 100, 2, ',', ''), $p->transaction_id, $p->valide_at?->format('d/m/Y'),
                            ], ';');
                        }
                    });
                    fclose($sortie);
                }, 'parrainages-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
        ];
    }
}

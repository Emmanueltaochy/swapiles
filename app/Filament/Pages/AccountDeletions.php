<?php

namespace App\Filament\Pages;

use App\Models\AccountDeletionReason;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

/**
 * Pourquoi les membres suppriment leur compte.
 *
 * Aucune donnée personnelle n'est conservée : uniquement le motif, la date,
 * l'ancienneté du compte et le fait qu'il y ait eu des ventes.
 */
class AccountDeletions extends Page
{
    protected static ?string $navigationLabel = 'Départs de membres';

    protected static ?string $title = 'Départs de membres';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-right-on-rectangle';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.account-deletions';

    public function getViewData(): array
    {
        if (! Schema::hasTable('account_deletion_reasons')) {
            return ['dispo' => false, 'motifs' => collect(), 'total' => 0, 'recents' => collect(), 'semaine' => 0];
        }

        $tous = AccountDeletionReason::query()->orderByDesc('id')->get();

        $motifs = $tous
            ->groupBy('reason')
            ->map(fn ($groupe, $cle) => [
                'label' => AccountDeletionReason::MOTIFS[$cle] ?? $cle,
                'total' => $groupe->count(),
            ])
            ->sortByDesc('total')
            ->values();

        return [
            'dispo' => true,
            'total' => $tous->count(),
            'semaine' => $tous->where('created_at', '>=', now()->subDays(7))->count(),
            'motifs' => $motifs,
            'recents' => $tous->filter(fn ($r) => filled($r->details))->take(30),
        ];
    }
}

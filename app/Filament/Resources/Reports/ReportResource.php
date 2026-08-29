<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\ListReports;
use App\Filament\Resources\Users\UserResource;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Signalements de contenu émis par les membres (annonces, membres, messages).
 * Exigence des stores Apple/Google pour toute app avec du contenu utilisateur.
 */
class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?int $navigationSort = 22;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('created_at')
                    ->label('Signalé le')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Report $r) => optional($r->created_at)->diffForHumans())
                    ->sortable(),

                TextColumn::make('status')
                    ->label('État')
                    ->badge()
                    ->formatStateUsing(fn (?string $s) => match ($s) {
                        'open' => 'À traiter',
                        'reviewed' => 'Traité',
                        'dismissed' => 'Rejeté',
                        default => $s ?: '—',
                    })
                    ->color(fn (?string $s) => match ($s) {
                        'open' => 'danger',
                        'reviewed' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('reason')
                    ->label('Motif')
                    ->badge()
                    ->formatStateUsing(fn (?string $s) => Report::REASONS[$s] ?? $s ?? '—')
                    ->color('warning'),

                TextColumn::make('reportable_type')
                    ->label('Cible')
                    ->formatStateUsing(fn (Report $r) => static::targetLabel($r))
                    ->url(fn (Report $r) => static::targetUrl($r))
                    ->color('primary')
                    ->wrap(),

                TextColumn::make('reporter.name')
                    ->label('Signalé par')
                    ->default('—')
                    ->description(fn (Report $r) => $r->reporter?->email)
                    ->searchable()
                    ->url(fn (Report $r) => $r->reporter_id ? UserResource::getUrl('view', ['record' => $r->reporter_id]) : null),

                TextColumn::make('details')
                    ->label('Détails')
                    ->default('—')
                    ->limit(60)
                    ->tooltip(fn (Report $r) => $r->details)
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('État')
                    ->options([
                        'open' => 'À traiter',
                        'reviewed' => 'Traité',
                        'dismissed' => 'Rejeté',
                    ])
                    ->default('open'),

                SelectFilter::make('reason')
                    ->label('Motif')
                    ->options(Report::REASONS),
            ])
            ->recordActions([
                Action::make('reviewed')
                    ->label('Marquer traité')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Report $r) => $r->status !== 'reviewed')
                    ->action(fn (Report $r) => $r->update(['status' => 'reviewed', 'handled_at' => now()])),

                Action::make('dismissed')
                    ->label('Rejeter')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn (Report $r) => $r->status !== 'dismissed')
                    ->action(fn (Report $r) => $r->update(['status' => 'dismissed', 'handled_at' => now()])),
            ]);
    }

    /** Libellé lisible de la cible signalée (annonce ou membre). */
    protected static function targetLabel(Report $r): string
    {
        return match ($r->reportable_type) {
            Listing::class => '📦 Annonce : ' . (optional($r->reportable)->title ?? '#' . $r->reportable_id),
            User::class => '👤 Membre : ' . (optional($r->reportable)->name ?? '#' . $r->reportable_id),
            \App\Models\Message::class => '💬 Message #' . $r->reportable_id,
            default => class_basename($r->reportable_type) . ' #' . $r->reportable_id,
        };
    }

    /** Lien vers la cible dans l'admin, quand c'est possible. */
    protected static function targetUrl(Report $r): ?string
    {
        if ($r->reportable_type === User::class) {
            return UserResource::getUrl('view', ['record' => $r->reportable_id]);
        }

        if ($r->reportable_type === Listing::class && $r->reportable) {
            return route('listings.show', $r->reportable_id);
        }

        return null;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['reporter', 'reportable']);
    }

    public static function getNavigationLabel(): string
    {
        return 'Signalements membres';
    }

    public static function getModelLabel(): string
    {
        return 'signalement';
    }

    public static function getPluralModelLabel(): string
    {
        return 'signalements';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Communauté';
    }

    /** Pastille : nombre de signalements à traiter. */
    public static function getNavigationBadge(): ?string
    {
        $count = Report::where('status', 'open')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReports::route('/'),
        ];
    }
}

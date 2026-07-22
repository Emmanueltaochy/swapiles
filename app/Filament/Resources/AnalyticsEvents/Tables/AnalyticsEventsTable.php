<?php

namespace App\Filament\Resources\AnalyticsEvents\Tables;

use App\Models\AnalyticsEvent;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnalyticsEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100, 200])
            ->defaultPaginationPageOption(50)
            ->poll('30s')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quand')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (AnalyticsEvent $record) => optional($record->created_at)->diffForHumans())
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Membre')
                    ->default('Visiteur anonyme')
                    ->description(fn (AnalyticsEvent $record) => $record->user?->email)
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('page_name')
                    ->label('Page')
                    ->default('—')
                    ->searchable(),
                TextColumn::make('path')
                    ->label('URL')
                    ->limit(40)
                    ->tooltip(fn (AnalyticsEvent $record) => $record->path)
                    ->searchable(),
                TextColumn::make('device')
                    ->label('Appareil')
                    ->badge()
                    ->color(fn (?string $state) => $state === 'Mobile' ? 'info' : 'gray')
                    ->toggleable(),
                TextColumn::make('browser')
                    ->label('Navigateur')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('connected')
                    ->label('Membres connectés')
                    ->placeholder('Tout le monde')
                    ->trueLabel('Connectés uniquement')
                    ->falseLabel('Anonymes uniquement')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('user_id'),
                        false: fn (Builder $query) => $query->whereNull('user_id'),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('range')
                    ->label('Période')
                    ->options([
                        'today' => 'Aujourd’hui',
                        '7d' => '7 derniers jours',
                        '30d' => '30 derniers jours',
                        '3m' => '3 mois',
                    ])
                    ->query(function (Builder $query, array $data) {
                        $start = match ($data['value'] ?? null) {
                            'today' => today(),
                            '7d' => now()->subDays(7),
                            '30d' => now()->subDays(30),
                            '3m' => now()->subMonths(3),
                            default => null,
                        };

                        return $start ? $query->where('created_at', '>=', $start) : $query;
                    }),
                SelectFilter::make('device')
                    ->label('Appareil')
                    ->options([
                        'Mobile' => 'Mobile',
                        'Desktop' => 'Desktop',
                        'Tablet' => 'Tablette',
                    ]),
            ]);
    }
}

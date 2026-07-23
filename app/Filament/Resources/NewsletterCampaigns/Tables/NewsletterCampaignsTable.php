<?php

namespace App\Filament\Resources\NewsletterCampaigns\Tables;

use App\Models\NewsletterCampaign;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NewsletterCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Envoyée le')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (NewsletterCampaign $r) => optional($r->created_at)->diffForHumans())
                    ->sortable(),

                TextColumn::make('subject')
                    ->label('Objet')
                    ->weight('bold')
                    ->limit(40)
                    ->searchable()
                    ->description(fn (NewsletterCampaign $r) => strtoupper($r->format)),

                TextColumn::make('sent_count')
                    ->label('Envoyés')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('open_rate')
                    ->label("Taux d'ouverture")
                    ->state(fn (NewsletterCampaign $r) => $r->uniqueOpens() . ' · ' . number_format($r->openRate(), 1, ',', ' ') . '%')
                    ->badge()
                    ->color(fn (NewsletterCampaign $r) => $r->openRate() >= 20 ? 'success' : ($r->openRate() >= 10 ? 'warning' : 'gray')),

                TextColumn::make('click_rate')
                    ->label('Taux de clic')
                    ->state(fn (NewsletterCampaign $r) => $r->uniqueClicks() . ' · ' . number_format($r->clickRate(), 1, ',', ' ') . '%')
                    ->badge()
                    ->color(fn (NewsletterCampaign $r) => $r->clickRate() >= 3 ? 'success' : ($r->clickRate() > 0 ? 'warning' : 'gray')),

                TextColumn::make('ctr')
                    ->label('CTR (clic/ouv.)')
                    ->state(fn (NewsletterCampaign $r) => number_format($r->ctr(), 1, ',', ' ') . '%')
                    ->toggleable(),
            ])
            ->recordActions([
                ViewAction::make()->label('Détails'),
            ]);
    }
}

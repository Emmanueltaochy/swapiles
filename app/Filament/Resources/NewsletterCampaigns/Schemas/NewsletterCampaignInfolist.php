<?php

namespace App\Filament\Resources\NewsletterCampaigns\Schemas;

use App\Models\NewsletterCampaign;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class NewsletterCampaignInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Campagne')
                    ->icon('heroicon-o-envelope')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('subject')->label('Objet')->weight('bold')->size('lg')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Envoyée le')->dateTime('d/m/Y à H:i')->since(),
                        TextEntry::make('format')->label('Format')->badge()->formatStateUsing(fn ($s) => strtoupper((string) $s)),
                        TextEntry::make('audience')->label('Audience')->badge()->placeholder('—'),
                    ]),

                Section::make('Performance')
                    ->icon('heroicon-o-chart-bar')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('sent_count')
                            ->label('E-mails envoyés')
                            ->badge()->color('gray')
                            ->formatStateUsing(fn ($s, NewsletterCampaign $r) => $s . ($r->failed_count ? ' (' . $r->failed_count . ' échec)' : '')),

                        TextEntry::make('open_rate')
                            ->label("Taux d'ouverture")
                            ->state(fn (NewsletterCampaign $r) => number_format($r->openRate(), 1, ',', ' ') . '%')
                            ->badge()
                            ->color(fn (NewsletterCampaign $r) => $r->openRate() >= 20 ? 'success' : ($r->openRate() >= 10 ? 'warning' : 'gray'))
                            ->hint(fn (NewsletterCampaign $r) => $r->uniqueOpens() . ' ouvertures uniques'),

                        TextEntry::make('click_rate')
                            ->label('Taux de clic')
                            ->state(fn (NewsletterCampaign $r) => number_format($r->clickRate(), 1, ',', ' ') . '%')
                            ->badge()
                            ->color(fn (NewsletterCampaign $r) => $r->clickRate() >= 3 ? 'success' : ($r->clickRate() > 0 ? 'warning' : 'gray'))
                            ->hint(fn (NewsletterCampaign $r) => $r->uniqueClicks() . ' clics uniques'),

                        TextEntry::make('ctr')
                            ->label('CTR (clic / ouverture)')
                            ->state(fn (NewsletterCampaign $r) => number_format($r->ctr(), 1, ',', ' ') . '%')
                            ->badge()->color('info'),

                        TextEntry::make('total_opens')
                            ->label('Ouvertures totales')
                            ->state(fn (NewsletterCampaign $r) => $r->totalOpens()),

                        TextEntry::make('total_clicks')
                            ->label('Clics totaux')
                            ->state(fn (NewsletterCampaign $r) => $r->totalClicks()),

                        TextEntry::make('not_opened')
                            ->label('Non ouverts')
                            ->state(fn (NewsletterCampaign $r) => max(0, $r->sent_count - $r->uniqueOpens())),

                        TextEntry::make('recipients_count')
                            ->label('Destinataires ciblés'),
                    ]),

                Section::make('Détail')
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->schema([
                        ViewEntry::make('detail')
                            ->view('filament.newsletter.campaign-detail')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

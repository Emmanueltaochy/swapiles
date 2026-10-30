<?php

namespace App\Filament\Resources\RelaisProspects\Pages;

use App\Console\Commands\EnvoyerProspectionRelais;
use App\Console\Commands\ProspecterRelais;
use App\Filament\Resources\RelaisProspects\RelaisProspectResource;
use App\Models\RelaisProspect;
use App\Support\ProspectionRelais;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class ListRelaisProspects extends ListRecords
{
    protected static string $resource = RelaisProspectResource::class;

    public function getSubheading(): ?string
    {
        $derniere = Cache::get('relais.prospection.derniere');
        $pause = Cache::get(EnvoyerProspectionRelais::PAUSE);

        return 'Commerces repérés sur OpenStreetMap qui pourraient devenir points relais. '
            . RelaisProspect::query()->contactable()->count() . ' à contacter par mail · '
            . ProspectionRelais::envoyesAujourdhui() . '/' . ProspectionRelais::plafondJour() . ' mails envoyés aujourd’hui · '
            . ($pause ? '⏸️ envoi automatique en pause' : 'envoi automatique chaque matin à 9 h, villes les plus demandées d’abord')
            . (($demandeAt = Cache::get(ProspecterRelais::DEMANDE . '_at')) && Cache::has(ProspecterRelais::DEMANDE)
                ? ' · ⏳ recherche demandée à ' . Carbon::parse($demandeAt)->format('H:i') . ', elle démarre dans la minute'
                : '')
            . ' · ' . (Cache::get(ProspecterRelais::BILAN) ?: ($derniere ? 'dernière recherche le ' . Carbon::parse($derniere)->format('d/m à H:i') : 'aucune recherche encore'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rechercher')
                ->label('Rechercher des commerces')
                ->icon('heroicon-o-magnifying-glass')
                ->schema([
                    Select::make('ile')->label('Île')->required()->default('toutes')
                        ->options(['toutes' => 'Toutes les îles'] + array_combine(array_keys(ProspectionRelais::ILES), array_keys(ProspectionRelais::ILES))),
                ])
                ->action(function (array $data) {
                    Cache::put(ProspecterRelais::DEMANDE, $data['ile'], now()->addHour());
                    Cache::put(ProspecterRelais::DEMANDE . '_at', now()->toIso8601String(), now()->addHour());
                    Notification::make()->title('Recherche lancée 🔎')
                        ->body('Elle démarre dans la minute et prend quelques minutes (carte, puis sites web des commerces). Rechargez la page ensuite.')
                        ->success()->send();
                }),
            Action::make('envoyerJour')
                ->label('Envoyer les mails maintenant')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn () => 'Envoie la présentation aux commerces « À contacter » qui ont un e-mail, villes les plus demandées d’abord, dans la limite de '
                    . ProspectionRelais::plafondJour() . ' par jour (déjà ' . ProspectionRelais::envoyesAujourdhui() . ' aujourd’hui).')
                ->action(function () {
                    $avant = ProspectionRelais::envoyesAujourdhui();
                    Artisan::call('relais:prospection-envoyer', ['--force' => true]);
                    Notification::make()->title((ProspectionRelais::envoyesAujourdhui() - $avant) . ' mail(s) envoyé(s)')->success()->send();
                }),
            Action::make('pause')
                ->label(fn () => Cache::get(EnvoyerProspectionRelais::PAUSE) ? 'Reprendre l’envoi auto' : 'Mettre l’envoi auto en pause')
                ->icon(fn () => Cache::get(EnvoyerProspectionRelais::PAUSE) ? 'heroicon-o-play' : 'heroicon-o-pause')
                ->color('gray')
                ->action(function () {
                    if (Cache::get(EnvoyerProspectionRelais::PAUSE)) {
                        Cache::forget(EnvoyerProspectionRelais::PAUSE);
                        Notification::make()->title('Envoi automatique repris')->success()->send();
                    } else {
                        Cache::forever(EnvoyerProspectionRelais::PAUSE, true);
                        Notification::make()->title('Envoi automatique en pause')->success()->send();
                    }
                }),
        ];
    }
}

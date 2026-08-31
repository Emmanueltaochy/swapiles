<?php

namespace App\Filament\Pages;

use App\Jobs\SendPushBroadcast;
use App\Models\DeviceToken;
use App\Support\FcmService;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * Outil d'envoi d'une notification push à tous les porteurs de l'application.
 */
class PushBroadcast extends Page
{
    protected static ?string $navigationLabel = 'Notifications push';
    protected static ?string $title = 'Notifications push';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.push-broadcast';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'title' => "Swap'Îles",
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(60)
                    ->helperText('Affiché en gras dans la notification.'),

                TextInput::make('body')
                    ->label('Message')
                    ->required()
                    ->maxLength(150)
                    ->helperText('Texte de la notification (court et incitatif).'),

                TextInput::make('url')
                    ->label('Lien à ouvrir (facultatif)')
                    ->url()
                    ->placeholder('https://swapiles.com/annonces')
                    ->helperText("Page ouverte quand l'utilisateur tape sur la notification."),
            ]);
    }

    public function send(): void
    {
        $state = $this->form->getState();

        if (! FcmService::configured()) {
            FilamentNotification::make()
                ->title('Notifications push non configurées')
                ->body('Le compte de service Firebase n’est pas encore installé sur le serveur. Envoi impossible pour le moment.')
                ->danger()
                ->send();

            return;
        }

        $count = DeviceToken::count();

        if ($count === 0) {
            FilamentNotification::make()
                ->title('Aucun appareil enregistré')
                ->body('Personne n’a encore installé l’app avec les notifications activées.')
                ->warning()
                ->send();

            return;
        }

        SendPushBroadcast::dispatch(
            title: (string) $state['title'],
            body: (string) $state['body'],
            url: $state['url'] ?? null,
        );

        FilamentNotification::make()
            ->title('Notification en cours d’envoi 🔔')
            ->body("Envoi lancé vers {$count} appareil(s).")
            ->success()
            ->send();

        $this->form->fill(['title' => "Swap'Îles"]);
    }
}

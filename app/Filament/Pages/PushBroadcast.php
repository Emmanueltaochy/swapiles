<?php

namespace App\Filament\Pages;

use App\Jobs\SendPushBroadcast;
use App\Models\DeviceToken;
use App\Support\ApnsService;
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

    /**
     * État du push, visible dans l'admin : sans accès au serveur, c'est le seul
     * moyen de voir pourquoi une notification ne part pas.
     */
    public function getViewData(): array
    {
        $appareils = DeviceToken::query()->get(['id', 'platform', 'token', 'last_seen_at']);

        $ios = $appareils->filter(fn (DeviceToken $d) => SendPushBroadcast::estIos($d));

        return [
            'total' => $appareils->count(),
            'iosCount' => $ios->count(),
            'androidCount' => $appareils->count() - $ios->count(),
            'fcmPret' => FcmService::configured(),
            'apnsPret' => ApnsService::configured(),
            'http2' => ApnsService::http2Available(),
            'apnsManquant' => $this->apnsManquant(),
        ];
    }

    /** Ce qu'il manque encore pour servir les iPhone. */
    private function apnsManquant(): array
    {
        $manque = [];

        $chemin = config('push.apns.key_path');
        if (! $chemin || ! is_file($chemin)) {
            $manque[] = 'la cle APNs (.p8) sur le serveur';
        }
        if (blank(config('push.apns.key_id'))) {
            $manque[] = "l'identifiant de la cle (APNS_KEY_ID)";
        }
        if (blank(config('push.apns.team_id'))) {
            $manque[] = "l'identifiant d'equipe Apple (APNS_TEAM_ID)";
        }

        return $manque;
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

        if (! FcmService::configured() && ! ApnsService::configured()) {
            FilamentNotification::make()
                ->title('Notifications push non configurées')
                ->body('Ni Apple (iPhone) ni Firebase (Android) ne sont configurés sur le serveur. Voir l’état ci-dessus.')
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

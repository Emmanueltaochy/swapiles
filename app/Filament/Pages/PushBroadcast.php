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

    /** Résultat du dernier test d'envoi, affiché tel quel dans la page. */
    public array $diagnostic = [];

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
        $appareils = DeviceToken::query()
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'platform', 'token', 'last_seen_at', 'last_result', 'last_error', 'last_sent_at']);

        $ios = $appareils->filter(fn (DeviceToken $d) => SendPushBroadcast::estIos($d));

        // Un jeton n'est pas un appareil : il change a chaque reinstallation.
        // L'app renvoie le sien a chaque lancement, donc « vu recemment »
        // correspond a une installation reellement vivante.
        $seuil = now()->subDays((int) config('push.active_days', 30));
        $actifs = $appareils->filter(fn (DeviceToken $d) => $d->last_seen_at && $d->last_seen_at->gte($seuil));

        return [
            'appareils' => $appareils,
            'total' => $appareils->count(),
            'actifs' => $actifs->count(),
            'obsoletes' => $appareils->count() - $actifs->count(),
            'joursActivite' => (int) config('push.active_days', 30),
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

    /**
     * Envoi de test IMMÉDIAT (hors file d'attente) vers les appareils
     * enregistrés, avec le résultat exact renvoyé par Apple ou Google.
     *
     * L'envoi normal passe par la file d'attente : en cas d'échec, rien n'est
     * visible depuis l'administration. Ce bouton existe pour voir la réponse
     * du service telle quelle.
     */
    public function testerEnvoi(): void
    {
        $appareils = DeviceToken::query()->orderByDesc('id')->limit(10)->get();

        if ($appareils->isEmpty()) {
            FilamentNotification::make()
                ->title('Aucun appareil enregistré')
                ->warning()
                ->send();

            return;
        }

        $fcm = app(FcmService::class);
        $apns = app(ApnsService::class);

        $this->diagnostic = $appareils->map(function (DeviceToken $device) use ($fcm, $apns) {
            $resultat = SendPushBroadcast::envoyerVers(
                $device,
                "Swap'Îles",
                'Test de notification 🔔',
                null,
                $fcm,
                $apns,
            );

            return [
                'plateforme' => SendPushBroadcast::estIos($device) ? 'iPhone / iPad' : 'Android',
                'jeton' => $device->tokenApercu(),
                'statut' => $resultat['status'],
                'erreur' => $resultat['error'],
            ];
        })->all();

        $reussis = collect($this->diagnostic)->where('statut', 'ok')->count();

        FilamentNotification::make()
            ->title($reussis . ' / ' . count($this->diagnostic) . ' envoi(s) accepté(s)')
            ->body($reussis === count($this->diagnostic)
                ? 'Le service a accepté la notification. Elle doit arriver sur l’appareil.'
                : 'Voir le détail ci-dessous : le message d’erreur du service y est repris mot pour mot.')
            ->status($reussis === count($this->diagnostic) ? 'success' : 'warning')
            ->send();
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

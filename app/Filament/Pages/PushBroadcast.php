<?php

namespace App\Filament\Pages;

use App\Jobs\SendPushBroadcast;
use App\Jobs\SendPushLot;
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
    protected static ?int $navigationSort = 1;

    protected static string | \UnitEnum | null $navigationGroup = 'Marketing';

    protected string $view = 'filament.pages.push-broadcast';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'title' => "Swap'Îles",
        ]);
    }

    /** Téléphones prêts à recevoir : appli ouverte récemment, notifications acceptées. */
    public function getViewData(): array
    {
        $seuil = now()->subDays((int) config('push.active_days', 30));
        $actifs = DeviceToken::query()->where('last_seen_at', '>=', $seuil)->get(['id', 'platform', 'token']);
        $ios = $actifs->filter(fn (DeviceToken $d) => SendPushBroadcast::estIos($d))->count();

        return [
            'ios' => $ios,
            'android' => $actifs->count() - $ios,
            'total' => $actifs->count(),
            'joursActivite' => (int) config('push.active_days', 30),
            'iosPret' => ApnsService::configured(),
            'androidPret' => FcmService::configured(),
            'interrompu' => $this->envoiInterrompu(),
        ];
    }

    /**
     * Dernière diffusion restée coupée en route (avant le découpage en
     * paquets) : combien de téléphones ne l'ont pas reçue.
     *
     * @return array{envoi:\App\Models\PushEnvoi,manquants:int}|null
     */
    public function envoiInterrompu(): ?array
    {
        $envoi = \App\Models\PushEnvoi::where('origine', 'manuel')->whereNull('user_id')
            ->where('statut', 'en_file')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->where('created_at', '>=', now()->subDays(2))
            ->latest('id')->first();

        if (! $envoi) {
            return null;
        }

        return ['envoi' => $envoi, 'manquants' => $this->idsManquants($envoi)->count()];
    }

    /** Téléphones qui n'ont rien reçu depuis le début de cet envoi. */
    private function idsManquants(\App\Models\PushEnvoi $envoi)
    {
        return DeviceToken::query()
            ->whereNotIn('id', DeviceToken::doublonsIds())
            ->where(fn ($q) => $q->whereNull('last_sent_at')->orWhere('last_sent_at', '<', $envoi->created_at))
            ->orderBy('id')
            ->pluck('id');
    }

    /** Envoie la diffusion coupée aux seuls téléphones qui ne l'ont pas reçue. */
    public function finirEnvoi(int $id): void
    {
        $envoi = \App\Models\PushEnvoi::where('origine', 'manuel')->where('statut', 'en_file')->find($id);
        if (! $envoi || ! \Illuminate\Support\Facades\Cache::add('push:finir:' . $envoi->id, 1, now()->addHour())) {
            FilamentNotification::make()->title('Déjà fait')->body('Cet envoi a déjà été terminé.')->warning()->send();

            return;
        }

        $ids = $this->idsManquants($envoi);
        $envoi->update(['statut' => 'envoye', 'envoye_at' => now(), 'appareils' => $envoi->appareils ?: $ids->count()]);
        $n = SendPushLot::planifier($ids, (string) $envoi->titre, (string) $envoi->message, \App\Support\PushSuivi::lien($envoi->url, $envoi->id), $envoi->id);

        FilamentNotification::make()
            ->title('Envoi terminé 🔔')
            ->body("La notification part vers les {$n} téléphone(s) qui ne l’avaient pas reçue. Ceux qui l’ont déjà ne la recevront pas une autre fois.")
            ->success()->send();
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
                    ->placeholder("Swap'Îles"),

                TextInput::make('body')
                    ->label('Message')
                    ->required()
                    ->maxLength(150)
                    ->placeholder('Ex : 🌴 Les nouveautés du week-end sont en ligne !'),

                TextInput::make('url')
                    ->label('Lien à ouvrir (facultatif)')
                    ->url()
                    ->placeholder('https://swapiles.com/recherche'),
            ]);
    }

    /** Envoie le message saisi sur les appareils de l'administrateur, pour le voir avant tout le monde. */
    public function apercu(): void
    {
        $state = $this->form->getState();
        $appareils = DeviceToken::query()->where('user_id', auth()->id())
            ->whereNotIn('id', DeviceToken::doublonsIds(auth()->id()))
            ->orderByDesc('id')->limit(10)->get();

        if ($appareils->isEmpty()) {
            FilamentNotification::make()
                ->title('Aucun téléphone à votre nom')
                ->body('Ouvrez l’appli connecté avec votre compte admin et acceptez les notifications.')
                ->warning()->send();

            return;
        }

        $fcm = app(FcmService::class);
        $apns = app(ApnsService::class);
        $ok = $appareils->filter(fn (DeviceToken $d) => SendPushBroadcast::envoyerVers(
            $d, (string) $state['title'], (string) $state['body'], $state['url'] ?? null, $fcm, $apns,
        )['status'] === 'ok')->count();

        FilamentNotification::make()
            ->title($ok > 0 ? 'Aperçu envoyé sur votre téléphone 📱' : 'L’aperçu n’est pas parti')
            ->body($ok > 0 ? 'Rien n’est parti chez les membres.' : 'Voir Système → Diagnostic notifications.')
            ->status($ok > 0 ? 'success' : 'warning')
            ->send();
    }

    public function send(): void
    {
        $state = $this->form->getState();

        if (! FcmService::configured() && ! ApnsService::configured()) {
            FilamentNotification::make()
                ->title('Notifications push non configurées')
                ->body('Ni Apple (iPhone) ni Firebase (Android) ne sont configurés. Voir Système → Diagnostic notifications.')
                ->danger()
                ->send();

            return;
        }

        $count = DeviceToken::count();

        // Double appui sur « Envoyer » (réseau lent sur téléphone) : la même
        // notification ne part qu'une fois en 10 minutes.
        $verrou = 'push:diffusion:' . sha1(mb_strtolower(trim((string) $state['title'])) . '|' . mb_strtolower(trim((string) $state['body'])));
        if ($count > 0 && ! \Illuminate\Support\Facades\Cache::add($verrou, 1, now()->addMinutes(10))) {
            FilamentNotification::make()
                ->title('Déjà envoyée')
                ->body('Cette notification vient d’être envoyée. Elle ne repart pas une deuxième fois.')
                ->warning()
                ->send();

            return;
        }

        if ($count === 0) {
            FilamentNotification::make()
                ->title('Aucun appareil enregistré')
                ->body('Personne n’a encore installé l’app avec les notifications activées.')
                ->warning()
                ->send();

            return;
        }

        $suivi = \App\Support\PushSuivi::journaliser([
            'origine' => 'manuel',
            'type' => 'diffusion',
            'titre' => (string) $state['title'],
            'message' => (string) $state['body'],
            'url' => $state['url'] ?? null,
            'statut' => 'en_file',
            'appareils' => $count,
        ]);

        SendPushBroadcast::dispatch(
            title: (string) $state['title'],
            body: (string) $state['body'],
            url: \App\Support\PushSuivi::lien($state['url'] ?? null, $suivi->id),
            envoiId: $suivi->id,
        );

        FilamentNotification::make()
            ->title('Notification en cours d’envoi 🔔')
            ->body("Envoi lancé vers {$count} appareil(s).")
            ->success()
            ->send();

        $this->form->fill(['title' => "Swap'Îles"]);
    }
}

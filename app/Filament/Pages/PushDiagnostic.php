<?php

namespace App\Filament\Pages;

use App\Jobs\SendPushBroadcast;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\ApnsService;
use App\Support\FcmService;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;

/**
 * Diagnostic des notifications push : état d'Apple et de Google, appareils
 * enregistrés, test d'envoi vers un compte. (Sorti de la page d'envoi, qui
 * ne garde que l'essentiel.)
 */
class PushDiagnostic extends Page
{
    protected static ?string $navigationLabel = 'Diagnostic notifications';
    protected static ?string $title = 'Diagnostic des notifications push';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';
    protected static ?int $navigationSort = 4;

    protected static string | \UnitEnum | null $navigationGroup = 'Système';

    protected string $view = 'filament.pages.push-diagnostic';

    /** Résultat du dernier test d'envoi, affiché tel quel dans la page. */
    public array $diagnostic = [];

    /** Compte visé par le test (vide = l'administrateur connecté). */
    public string $emailTest = '';

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
            $manque[] = 'la cle APNs (.p8) sur le serveur (secret GitHub APNS_KEY_P8 : contenu du fichier colle tel quel)';
        }
        if (blank(config('push.apns.key_id'))) {
            $manque[] = "l'identifiant de la cle (APNS_KEY_ID)";
        }
        if (blank(config('push.apns.team_id'))) {
            $manque[] = "l'identifiant d'equipe Apple (APNS_TEAM_ID)";
        }

        return $manque;
    }

    /**
     * Envoi de test IMMÉDIAT (hors file d'attente) vers les appareils d'UN
     * SEUL compte : celui dont l'e-mail est saisi, ou à défaut
     * l'administrateur connecté. Affiche le résultat exact renvoyé par Apple
     * ou Google. (Il partait auparavant aux 10 derniers appareils
     * enregistrés, donc chez de vrais membres au hasard.)
     *
     * L'envoi normal passe par la file d'attente : en cas d'échec, rien n'est
     * visible depuis l'administration. Ce bouton existe pour voir la réponse
     * du service telle quelle.
     */
    public function testerEnvoi(): void
    {
        $this->diagnostic = [];
        $email = trim($this->emailTest);

        if ($email === '') {
            $cible = auth()->user();
        } else {
            $cible = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();

            if (! $cible) {
                FilamentNotification::make()
                    ->title('Aucun compte avec cette adresse')
                    ->body($email . ' : vérifiez l’orthographe. Rien n’a été envoyé.')
                    ->danger()
                    ->send();

                return;
            }
        }

        $appareils = DeviceToken::query()
            ->where('user_id', $cible->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($appareils->isEmpty()) {
            FilamentNotification::make()
                ->title($email === '' ? 'Aucun appareil à votre nom' : 'Aucun appareil pour ' . $cible->email)
                ->body('Il faut ouvrir l’appli Swap’Îles sur le téléphone en étant connecté avec CE compte et accepter les notifications, puis réessayer. Rien n’a été envoyé.')
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
}

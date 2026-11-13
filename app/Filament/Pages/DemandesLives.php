<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\InteretLive;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** Résultats du sondage « live shopping » et demandes pour organiser des lives (accepter / refuser). */
class DemandesLives extends Page
{
    protected static ?string $navigationLabel = 'Demandes de lives';

    protected static ?string $title = 'Live shopping : sondage et demandes';

    protected static ?string $slug = 'demandes-lives';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-video-camera';

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.demandes-lives';

    /** Choix affiché dans la liste « qui a voté quoi » (null = tous). */
    public ?string $filtre = null;

    public function filtrer(?string $choix): void
    {
        $this->filtre = $choix !== null && array_key_exists($choix, InteretLive::CHOIX) && $this->filtre !== $choix ? $choix : null;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = User::where('live_demande_statut', 'en_attente')->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getViewData(): array
    {
        $reponses = User::whereNotNull('live_interet')->selectRaw('live_interet, COUNT(*) n')->groupBy('live_interet')->pluck('n', 'live_interet')->all();

        return [
            'reponses' => $reponses,
            'total' => array_sum($reponses),
            'enAttente' => User::where('live_demande_statut', 'en_attente')->orderBy('live_demande_at')->get()
                ->map(fn (User $u) => ['u' => $u, 'p' => InteretLive::profil($u)]),
            'traitees' => User::whereIn('live_demande_statut', ['acceptee', 'refusee'])->orderByDesc('updated_at')->limit(20)->get(),
            'autorises' => User::where('live_autorise', true)->count(),
            'votants' => User::whereNotNull('live_interet')
                ->when($this->filtre, fn ($q) => $q->where('live_interet', $this->filtre))
                ->orderByDesc('live_interet_at')
                ->limit(300)
                ->get(['id', 'name', 'email', 'territoire', 'is_pro', 'live_interet', 'live_interet_at', 'live_demande_statut']),
        ];
    }

    public function accepter(int $id): void
    {
        $u = User::where('live_demande_statut', 'en_attente')->find($id);
        if ($u) {
            InteretLive::accepter($u);
            Notification::make()->title($u->name . ' peut faire des lives 🎉')->body('Il a reçu une notification.')->success()->send();
        }
    }

    public function refuser(int $id): void
    {
        $u = User::where('live_demande_statut', 'en_attente')->find($id);
        if ($u) {
            InteretLive::refuser($u);
            Notification::make()->title('Demande refusée')->body($u->name . ' a reçu un message bienveillant.')->send();
        }
    }
}

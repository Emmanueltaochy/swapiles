<?php

namespace App\Filament\Pages;

use App\Models\AgentRapport;
use App\Models\AgentReponse;
use App\Models\ProspectRse;
use App\Models\Reglage;
use App\Support\Agent\BoiteReponses;
use App\Support\Agent\Ia;
use App\Support\Agent\Prospection;
use App\Support\Agent\ProspectionRse;
use App\Support\Agent\RapportQuotidien;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Bureau du CEO : le résumé du jour de l'agent directeur, ses 3 actions
 * (Go / Non), la prospection (pause, validation du modèle collectivités)
 * et les réponses des prospects avec un brouillon de réponse.
 */
class BureauDuCeo extends Page
{
    protected static ?string $navigationLabel = 'Bureau du CEO';

    protected static ?string $title = 'Bureau du CEO (agent directeur)';

    protected static ?string $slug = 'bureau-du-ceo';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'Direction';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.bureau-du-ceo';

    public bool $voirApercu = false;

    /** Réservé à l'administrateur (Emmanuel) : jamais visible des membres. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    /** Derniers échanges avec le CEO (pour les questions de suite). */
    public array $echanges = [];

    public function getViewData(): array
    {
        $exemple = ProspectRse::query()->where('type', 'ccas')->first() ?? ProspectRse::query()->first();

        return [
            'rapport' => AgentRapport::orderByDesc('jour')->first(),
            'reponses' => AgentReponse::orderByRaw('vu_at IS NULL DESC')->orderByDesc('recu_at')->limit(40)->get(),
            'p' => [
                'pause' => Prospection::enPause(),
                'boite' => Prospection::boiteSeparee(),
                'lecture' => BoiteReponses::configuree(),
                'ia' => Ia::disponible(),
                'cout' => Ia::depenseDuMois(),
                'budget' => (float) config('agent.budget_mensuel_usd'),
                'plafond' => Prospection::plafondJour(),
                'envoyes' => Prospection::envoyesAujourdhui(),
                'rse_valide' => Prospection::rseValidee(),
                'organismes' => ProspectRse::count(),
                'organismes_email' => ProspectRse::whereNotNull('email')->count(),
                'organismes_contactes' => ProspectRse::whereNotNull('contacte_at')->count(),
                'par_type' => ProspectRse::selectRaw('type, COUNT(*) n')->groupBy('type')->pluck('n', 'type')->all(),
            ],
            'apercu' => $this->voirApercu && $exemple
                ? view('emails.prospection-rse', ProspectionRse::donnees($exemple))->render()
                : null,
            'exemple' => $exemple,
        ];
    }

    public function decider(int $rapportId, int $actionId, string $decision): void
    {
        $r = AgentRapport::find($rapportId);
        if (! $r || ! in_array($decision, ['go', 'non'], true)) {
            return;
        }
        $r->actions = collect($r->actions ?? [])->map(function ($a) use ($actionId, $decision) {
            if (($a['id'] ?? null) === $actionId) {
                $a['decision'] = $decision;
            }

            return $a;
        })->all();
        $r->save();
        Notification::make()->title($decision === 'go' ? 'C’est noté : Go ✅' : 'C’est noté : on laisse de côté')->success()->send();
    }

    public function genererMaintenant(): void
    {
        RapportQuotidien::generer(false);
        Notification::make()->title('Résumé du jour mis à jour')->success()->send();
    }

    public function basculerPause(): void
    {
        Reglage::ecrire(Prospection::PAUSE, ! (bool) Reglage::lire(Prospection::PAUSE, false));
        Notification::make()->title(Prospection::enPause() ? 'Prospection en pause ⏸️' : 'Prospection relancée ▶️')->success()->send();
    }

    public function basculerApercu(): void
    {
        $this->voirApercu = ! $this->voirApercu;
    }

    public function validerRse(): void
    {
        Reglage::ecrire(Prospection::RSE_VALIDE, ! Prospection::rseValidee());
        Notification::make()->title(Prospection::rseValidee() ? 'Modèle validé : les collectivités seront contactées ✅' : 'Envois aux collectivités arrêtés')->success()->send();
    }

    /** « Parler au CEO » : renvoie la réponse (lue à voix haute par la page). */
    public function demander(string $question): string
    {
        abort_unless(static::canAccess(), 403);
        $reponse = \App\Support\Agent\Conversation::repondre($question, $this->echanges);
        $this->echanges[] = ['q' => \Illuminate\Support\Str::limit($question, 500), 'r' => $reponse];
        $this->echanges = array_slice($this->echanges, -6);
        $this->skipRender();

        return $reponse;
    }

    public function oublier(): void
    {
        $this->echanges = [];
    }

    public function marquerVu(int $id): void
    {
        AgentReponse::whereKey($id)->update(['vu_at' => now()]);
    }
}

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
            'candidatures' => \App\Models\CandidatureRelais::where('statut', 'nouvelle')->orderBy('created_at')->get(),
            'aRappeler' => AgentReponse::whereNull('vu_at')->whereIn('categorie', ['interesse', 'question'])->orderBy('recu_at')->get(),
            'reponses' => AgentReponse::where(fn ($q) => $q->whereNotNull('vu_at')->orWhereNotIn('categorie', ['interesse', 'question']))
                ->orderByDesc('recu_at')->limit(40)->get(),
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
                'envoi_contact' => \App\Support\Agent\EnvoiReponse::disponible(),
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

    /** Réponse de prospect prête à partir, en attente du « oui, envoie » d'Emmanuel. */
    public ?int $envoiEnAttente = null;

    /**
     * « Parler au CEO » : renvoie le texte à lire à voix haute et, s'il y a
     * un e-mail prêt à partir, son aperçu.
     *
     * @return array{texte:string,apercu:?array}
     */
    public function demander(string $question): array
    {
        abort_unless(static::canAccess(), 403);
        $res = \App\Support\Agent\Conversation::repondre($question, $this->echanges, $this->envoiEnAttente);
        $this->envoiEnAttente = $res['enAttente'];
        $this->echanges[] = ['q' => \Illuminate\Support\Str::limit($question, 500), 'r' => $res['texte']];
        $this->echanges = array_slice($this->echanges, -6);
        $this->skipRender();

        return ['texte' => $res['texte'], 'apercu' => $res['apercu']];
    }

    /** Bouton « Envoyer depuis contact@ » d'une fiche (confirmation demandée par la page). */
    public function envoyerReponse(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $r = AgentReponse::find($id);
        try {
            \App\Support\Agent\EnvoiReponse::envoyer($r);
            Notification::make()->title('Réponse envoyée depuis contact@ ✉️')->success()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Pas envoyé : ' . ($e instanceof \RuntimeException && ! str_contains($e->getMessage(), '@') ? $e->getMessage() : 'le serveur d’e-mail a refusé'))->danger()->send();
        }
        if ($this->envoiEnAttente === $id) {
            $this->envoiEnAttente = null;
        }
    }

    public function oublier(): void
    {
        $this->echanges = [];
        $this->envoiEnAttente = null;
    }

    /** Suivi d'un prospect qui a répondu : rendez-vous pris, traité, finalement pas intéressé, devenu point relais. */
    public function suivre(int $id, string $action): void
    {
        $r = AgentReponse::find($id);
        $p = $r?->prospect();
        if (! $r || ! $p || ! in_array($action, ['rdv', 'traite', 'refus', 'relais'], true)) {
            return;
        }
        $note = fn (string $t) => trim(($p->notes ? $p->notes . "\n" : '') . now()->format('d/m/Y') . ' : ' . $t);
        match ($action) {
            'rdv' => $p->forceFill(['statut' => 'interesse', 'notes' => $note('rendez-vous pris')])->save(),
            'refus' => $p->forceFill(['statut' => 'refuse', 'notes' => $note('finalement pas intéressé')])->save(),
            'relais' => $p instanceof \App\Models\RelaisProspect ? $p->forceFill(['statut' => 'relais', 'notes' => $note('devenu point relais')])->save() : null,
            default => null,
        };
        $r->forceFill(['vu_at' => now()])->save();
        Notification::make()->title(match ($action) {
            'rdv' => 'Rendez-vous noté 📅',
            'refus' => 'Noté : pas intéressé',
            'relais' => 'Bravo : nouveau point relais 🏪',
            default => 'Marqué comme traité ✅',
        })->success()->send();
    }

    public function marquerVu(int $id): void
    {
        AgentReponse::whereKey($id)->update(['vu_at' => now()]);
    }
}

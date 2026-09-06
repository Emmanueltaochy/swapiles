<?php

namespace App\Filament\Pages;

use App\Models\SentEmail;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Schema;

/**
 * Volume d'e-mails envoyés, par jour et par type.
 *
 * La boîte d'envoi est plafonnée à 1 000 e-mails par jour par l'hébergeur.
 * Au-delà, tous les envois sont ralentis : les e-mails importants arrivent
 * des heures plus tard ou en indésirable. Cette page montre ce qui consomme
 * le quota, à partir des envois réellement enregistrés.
 */
class EmailVolume extends Page
{
    protected static ?string $navigationLabel = "Volume d'e-mails";

    protected static ?string $title = "Volume d'e-mails";

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.email-volume';

    /** Plafond quotidien de l'hébergeur. */
    public const QUOTA_JOUR = 1000;

    public function getViewData(): array
    {
        if (! Schema::hasTable('sent_emails')) {
            return ['dispo' => false, 'jours' => collect(), 'types' => collect(), 'quota' => self::QUOTA_JOUR];
        }

        $depuis = now()->subDays(14)->startOfDay();

        $jours = SentEmail::query()
            ->where('created_at', '>=', $depuis)
            ->selectRaw('DATE(created_at) as jour, COUNT(*) as total')
            ->groupBy('jour')
            ->orderByDesc('jour')
            ->get();

        // On regroupe par « famille » d'e-mail : les objets contiennent des
        // titres d'annonces ou des prénoms, il faut les normaliser pour compter.
        $types = SentEmail::query()
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->get(['subject'])
            ->groupBy(fn (SentEmail $e) => $this->famille((string) $e->subject))
            ->map(fn ($groupe, $famille) => ['famille' => $famille, 'total' => $groupe->count()])
            ->sortByDesc('total')
            ->values();

        return [
            'dispo' => true,
            'jours' => $jours,
            'types' => $types,
            'quota' => self::QUOTA_JOUR,
            'total7j' => $types->sum('total'),
        ];
    }

    /** Réduit un objet d'e-mail à sa famille (sans titre d'annonce ni prénom). */
    private function famille(string $subject): string
    {
        $s = trim($subject);

        $regles = [
            'Confirmez votre adresse' => "Confirmation d'adresse e-mail",
            'Bienvenue' => 'Bienvenue (inscription)',
            'regardé' => 'Annonce consultée',
            'vue' => 'Annonce consultée',
            'favori' => 'Favori',
            'message' => 'Message reçu / relance',
            'mot de passe' => 'Mot de passe oublié',
            'vente' => 'Vente / transaction',
            'paiement' => 'Vente / transaction',
            'commande' => 'Vente / transaction',
            'colis' => 'Vente / transaction',
            'offre' => 'Offre de prix',
            'annonce' => 'Annonce (autre)',
            'IBAN' => 'Rappel IBAN',
            'Swap' => 'Divers Swap’Îles',
        ];

        foreach ($regles as $motif => $famille) {
            if (mb_stripos($s, $motif) !== false) {
                return $famille;
            }
        }

        return 'Autre';
    }
}

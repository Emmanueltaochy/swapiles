<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** « Je voudrais un point relais près de chez moi » (page Nos points relais). */
class RelayPointRequest extends Model
{
    /** Réseaux de points relais existants, pour savoir si le commerce connaît déjà. */
    public const RESEAUX = [
        'mondial_relay' => 'Mondial Relay',
        'relais_colis' => 'Relais Colis',
        'pickup' => 'Pickup (Chronopost / DPD)',
        'la_poste' => 'Colissimo / La Poste',
        'amazon' => 'Amazon',
        'autre' => 'Autre',
    ];

    public const DEJA_RELAIS = [
        'oui' => 'Oui',
        'non' => 'Non',
        'ne_sait_pas' => 'Je ne sais pas',
    ];

    public const STATUTS = [
        'nouvelle' => 'Nouvelle',
        'contactee' => 'Commerce contacté',
        'ouverte' => 'Point relais ouvert',
        'sans_suite' => 'Sans suite',
    ];

    protected $fillable = [
        'user_id', 'territoire', 'city', 'business_name', 'business_address',
        'already_relay', 'networks', 'name', 'email', 'phone', 'message', 'status',
    ];

    protected $casts = [
        'networks' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reseauxLisibles(): string
    {
        return collect($this->networks ?? [])->map(fn ($r) => self::RESEAUX[$r] ?? $r)->implode(', ');
    }

    /** E-mail envoyé à l'équipe (contact@swapiles.com). */
    public function corpsEmail(): string
    {
        $lignes = [
            'Nouvelle demande de point relais',
            '',
            'Île : ' . $this->territoire,
            'Ville / quartier : ' . $this->city,
            'Commerce proposé : ' . ($this->business_name ?: '—'),
            'Adresse ou repère : ' . ($this->business_address ?: '—'),
            'Déjà point relais : ' . (self::DEJA_RELAIS[$this->already_relay] ?? '—')
                . ($this->reseauxLisibles() ? ' (' . $this->reseauxLisibles() . ')' : ''),
            '',
            'Détail ajouté : ' . (filled($this->message) ? "\n" . trim($this->message) : '—'),
            '',
            'Demandé par : ' . $this->name,
            'E-mail : ' . $this->email,
            'Téléphone : ' . ($this->phone ?: '—'),
        ];

        $membre = $this->user;
        if ($membre) {
            $lignes[] = '';
            $lignes[] = 'Membre Swap’Îles : ' . $membre->name . ' (inscrit le ' . $membre->created_at?->format('d/m/Y') . ')';
            $lignes[] = 'Fiche membre (admin) : ' . \App\Filament\Resources\Users\UserResource::getUrl('view', ['record' => $membre]);
            $lignes[] = 'Profil public : ' . route('profiles.show', $membre);
        } else {
            $lignes[] = '';
            $lignes[] = 'Demande faite sans compte Swap’Îles.';
        }

        $lignes[] = '';
        $lignes[] = 'Toutes les demandes : ' . \App\Filament\Resources\RelayPointRequests\RelayPointRequestResource::getUrl('index');

        return implode("\n", $lignes) . "\n";
    }
}

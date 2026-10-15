<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un filleul et son parrain, du jour de l'inscription à la récompense. */
class Parrainage extends Model
{
    protected $guarded = [];

    protected $casts = [
        'valide_at' => 'datetime',
        'annule_at' => 'datetime',
    ];

    public const STATUTS = [
        'inscrit' => 'Inscrit',
        'achat_en_cours' => '1er achat en attente',
        'valide' => 'Validé',
        'refuse' => 'Refusé',
        'annule' => 'Annulé',
    ];

    public const MOTIFS = [
        'meme_appareil' => 'Même appareil que le parrain',
        'meme_carte' => 'Même carte bancaire que le parrain',
        'meme_email' => 'Même adresse e-mail (alias)',
        'meme_telephone' => 'Même numéro de téléphone',
        'plafond_mensuel' => 'Plafond mensuel du parrain atteint',
        'parrain_indisponible' => 'Compte du parrain suspendu ou supprimé',
        'programme_inactif' => 'Programme désactivé',
        'annulation_admin' => 'Annulé par l’admin',
    ];

    public function parrain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parrain_id');
    }

    public function filleul(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filleul_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function montantEuros(): float
    {
        return $this->montant_centimes / 100;
    }
}

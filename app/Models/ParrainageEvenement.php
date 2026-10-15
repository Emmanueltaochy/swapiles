<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Journal du parrainage (mesure + tentatives acceptées/refusées pour l'admin). */
class ParrainageEvenement extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['donnees' => 'array', 'created_at' => 'datetime'];

    public const LIBELLES = [
        'referral_link_shared' => 'Lien partagé',
        'referral_link_opened' => 'Lien ouvert',
        'referral_signup' => 'Inscription filleul',
        'referral_first_purchase' => '1er achat payé',
        'referral_reward_granted' => 'Récompense accordée',
        'referral_reward_rejected' => 'Récompense refusée',
        'referral_reward_cancelled' => 'Récompense annulée (admin)',
        'referral_code_rejected' => 'Code refusé',
        'referral_code_customized' => 'Code personnalisé',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parrainage(): BelongsTo
    {
        return $this->belongsTo(Parrainage::class);
    }
}

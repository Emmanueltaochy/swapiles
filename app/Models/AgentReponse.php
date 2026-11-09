<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Réponse d'un commerce ou d'un organisme prospecté, lue sur contact@ et triée par l'agent. */
class AgentReponse extends Model
{
    public const CATEGORIES = [
        'interesse' => '🔥 Intéressé',
        'question' => '❓ Question',
        'refus' => 'Pas intéressé',
        'desinscription' => 'Ne plus écrire',
        'absence' => 'Absence / réponse automatique',
        'autre' => 'Autre',
    ];

    protected $fillable = ['message_id', 'prospect_type', 'prospect_id', 'expediteur', 'sujet', 'recu_at', 'categorie', 'resume', 'brouillon', 'extrait', 'vu_at'];

    protected $casts = ['recu_at' => 'datetime', 'vu_at' => 'datetime'];

    public function prospect(): RelaisProspect|ProspectRse|null
    {
        return $this->prospect_type === 'rse' ? ProspectRse::find($this->prospect_id) : RelaisProspect::find($this->prospect_id);
    }
}

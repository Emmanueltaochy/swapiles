<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Résumé quotidien de l'agent directeur (chiffres + 3 actions proposées). */
class AgentRapport extends Model
{
    protected $fillable = ['jour', 'chiffres', 'texte', 'actions', 'cout_usd'];

    protected $casts = ['jour' => 'date', 'chiffres' => 'array', 'actions' => 'array'];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Point 17b : abonnement « préviens-moi » sur un terme de recherche resté
 * sans résultat. La capture est immédiate ; l'envoi de l'alerte est différé
 * (aucun e-mail tant que DKIM/délivrabilité n'est pas réglé).
 */
class SearchAlert extends Model
{
    protected $fillable = [
        'term', 'raw_term', 'email', 'user_id', 'visitor_id', 'territoire', 'notified_at',
    ];

    protected $casts = ['notified_at' => 'datetime'];

    /** Normalise un terme pour regrouper les variantes (casse/espaces). */
    public static function normalize(string $raw): string
    {
        $t = mb_strtolower(trim($raw));

        return trim(preg_replace('/\s+/', ' ', $t));
    }
}

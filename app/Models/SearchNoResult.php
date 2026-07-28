<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchNoResult extends Model
{
    public $timestamps = false;

    protected $fillable = ['term', 'raw_term', 'user_id', 'visitor_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    /** Normalise une requête pour regrouper les variantes (casse/espaces). */
    public static function normalize(string $raw): string
    {
        $t = mb_strtolower(trim($raw));

        return trim(preg_replace('/\s+/', ' ', $t));
    }
}

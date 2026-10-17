<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Une recherche tapée par un visiteur (avec ou sans résultat). */
class SearchLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['term', 'raw_term', 'resultats', 'user_id', 'visitor_id', 'territoire', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}

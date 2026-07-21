<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveVisit extends Model
{
    protected $fillable = [
        'ip_hash',
        'user_id',
        'territoire',
        'url',
        'path',
        'device',
        'lat',
        'lng',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];
}

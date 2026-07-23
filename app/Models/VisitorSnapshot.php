<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'live_count',
        'members_count',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SentEmail extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'to_email',
        'to_name',
        'subject',
        'mailer',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}

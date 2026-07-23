<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id', 'recipient_id', 'type', 'url', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['live_session_id', 'user_id', 'body', 'type', 'masque', 'created_at'];

    protected $casts = ['masque' => 'boolean', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

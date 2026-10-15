<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushClic extends Model
{
    protected $table = 'push_clics';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function envoi(): BelongsTo
    {
        return $this->belongsTo(PushEnvoi::class, 'push_envoi_id');
    }
}

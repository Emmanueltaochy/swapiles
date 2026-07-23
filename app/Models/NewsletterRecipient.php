<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterRecipient extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id', 'email', 'token',
        'opened_at', 'first_clicked_at', 'open_count', 'click_count',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'first_clicked_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'campaign_id');
    }
}

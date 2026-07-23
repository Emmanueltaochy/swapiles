<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsletterCampaign extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'subject', 'format', 'audience',
        'recipients_count', 'sent_count', 'failed_count',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(NewsletterRecipient::class, 'campaign_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(NewsletterEvent::class, 'campaign_id');
    }

    /* ---- Métriques ---- */

    public function uniqueOpens(): int
    {
        return (int) $this->recipients()->whereNotNull('opened_at')->count();
    }

    public function uniqueClicks(): int
    {
        return (int) $this->recipients()->whereNotNull('first_clicked_at')->count();
    }

    public function totalOpens(): int
    {
        return (int) $this->recipients()->sum('open_count');
    }

    public function totalClicks(): int
    {
        return (int) $this->recipients()->sum('click_count');
    }

    public function openRate(): float
    {
        return $this->sent_count > 0 ? round($this->uniqueOpens() / $this->sent_count * 100, 1) : 0.0;
    }

    public function clickRate(): float
    {
        return $this->sent_count > 0 ? round($this->uniqueClicks() / $this->sent_count * 100, 1) : 0.0;
    }

    /** Click-to-open rate : clics uniques / ouvertures uniques. */
    public function ctr(): float
    {
        $opens = $this->uniqueOpens();

        return $opens > 0 ? round($this->uniqueClicks() / $opens * 100, 1) : 0.0;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'sharetribe_id',
        'listing_id',
        'sender_id',
        'receiver_id',
        'body',
        'attachment_path',
        'attachment_type',
        'attachment_mime',
        'read_at',
        'reminder_sent_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function hasAttachment(): bool
    {
        return filled($this->attachment_path);
    }

    public function isImageAttachment(): bool
    {
        return $this->attachment_type === 'image';
    }

    public function isVideoAttachment(): bool
    {
        return $this->attachment_type === 'video';
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->attachment_path)
            : null;
    }

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}

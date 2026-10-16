<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un article mis de côté dans le lot en préparation d'un acheteur. */
class PanierArticle extends Model
{
    protected $fillable = ['buyer_id', 'listing_id'];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}

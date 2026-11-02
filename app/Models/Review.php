<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'sharetribe_id', 'transaction_id', 'reviewer_id',
        'reviewed_id', 'rating', 'comment',
    ];

    protected static function booted(): void
    {
        // Avis de 4 ou 5 étoiles : un moment heureux pour les deux membres
        // (celui qui note et celui qui est noté). La fenêtre de notation de
        // l'appli leur sera proposée à leur prochaine visite dans l'appli.
        static::created(function (self $avis) {
            if ($avis->sharetribe_id || (int) $avis->rating < 4) {
                return;
            }
            try {
                \App\Support\NoteAppli::momentHeureux(User::find($avis->reviewer_id));
                \App\Support\NoteAppli::momentHeureux(User::find($avis->reviewed_id));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewed()
    {
        return $this->belongsTo(User::class, 'reviewed_id');
    }
}

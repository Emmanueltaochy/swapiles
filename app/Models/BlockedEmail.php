<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * E-mail sur liste noire. Persiste indépendamment des comptes : bloquer un
 * membre (ou supprimer un compte problématique) inscrit son e-mail ici, ce qui
 * empêche toute réinscription/connexion ultérieure avec la même adresse.
 */
class BlockedEmail extends Model
{
    protected $fillable = ['email', 'reason', 'blocked_by'];

    protected static function booted(): void
    {
        // Normalise systématiquement l'e-mail (y compris saisies admin manuelles).
        static::saving(function (BlockedEmail $model) {
            $model->email = static::normalize($model->email);
        });
    }

    /** Normalise un e-mail pour comparaison (minuscule, sans espaces). */
    public static function normalize(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    /** L'e-mail est-il bloqué ? */
    public static function isBlocked(?string $email): bool
    {
        $normalized = static::normalize($email);

        if ($normalized === '') {
            return false;
        }

        return static::where('email', $normalized)->exists();
    }

    /** Bloque un e-mail (idempotent). */
    public static function block(?string $email, ?string $reason = null, ?int $blockedBy = null): void
    {
        $normalized = static::normalize($email);

        if ($normalized === '') {
            return;
        }

        static::updateOrCreate(
            ['email' => $normalized],
            ['reason' => $reason ? mb_substr($reason, 0, 255) : null, 'blocked_by' => $blockedBy],
        );
    }

    /** Débloque un e-mail. */
    public static function unblock(?string $email): void
    {
        $normalized = static::normalize($email);

        if ($normalized !== '') {
            static::where('email', $normalized)->delete();
        }
    }
}

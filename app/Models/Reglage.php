<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Réglage durable (clé → valeur) : contrairement au cache, survit aux mises en ligne. */
class Reglage extends Model
{
    protected $table = 'reglages';

    protected $primaryKey = 'cle';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['cle', 'valeur'];

    public static function lire(string $cle, mixed $defaut = null): mixed
    {
        try {
            $v = static::query()->whereKey($cle)->value('valeur');
        } catch (\Throwable $e) {
            return $defaut;
        }

        return $v === null ? $defaut : json_decode($v, true);
    }

    public static function ecrire(string $cle, mixed $valeur): void
    {
        static::query()->updateOrCreate(['cle' => $cle], ['valeur' => json_encode($valeur, JSON_UNESCAPED_UNICODE)]);
    }
}

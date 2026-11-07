<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Petits comptes rendus anonymes (dépôt de photos, envois coupés, lives)
 * rangés dans des fichiers : contrairement au cache, ils survivent aux
 * mises en ligne (chaque déploiement vide le cache).
 */
class CarnetDiagnostic
{
    private static function chemin(string $nom): string
    {
        return 'diagnostic/' . preg_replace('/[^a-z0-9_-]/', '', $nom) . '.json';
    }

    /** Ajoute un cas en tête et garde les $max plus récents. */
    public static function ajouter(string $nom, array $cas, int $max = 50): void
    {
        try {
            $disque = Storage::disk('local');
            $chemin = self::chemin($nom);
            $liste = self::lire($nom);
            array_unshift($liste, $cas);
            $disque->put($chemin, json_encode(array_slice($liste, 0, $max), JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            // Le suivi ne doit jamais gêner le site.
        }
    }

    /** Les cas, du plus récent au plus ancien (30 derniers jours). */
    public static function lire(string $nom): array
    {
        try {
            $brut = Storage::disk('local')->get(self::chemin($nom));
            $liste = is_string($brut) ? json_decode($brut, true) : null;
        } catch (\Throwable $e) {
            return [];
        }
        if (! is_array($liste)) {
            return [];
        }
        $limite = now()->subDays(30)->format('Y-m-d H:i');

        return array_values(array_filter($liste, fn ($c) => is_array($c) && ($c['quand'] ?? '') >= $limite));
    }

    /** Le cas le plus récent, ou null. */
    public static function dernier(string $nom): ?array
    {
        return self::lire($nom)[0] ?? null;
    }
}

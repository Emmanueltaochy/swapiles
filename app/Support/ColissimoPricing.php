<?php

namespace App\Support;

use RuntimeException;

class ColissimoPricing
{
    public static function calculate(string $origin, string $destination, float $weightKg): array
    {
        $origin = self::normalizeTerritory($origin);
        $destination = self::normalizeTerritory($destination);

        $zone = self::zone($origin, $destination);
        $ht = self::priceHt($zone, $weightKg);
        $ttc = round($ht * 1.085, 2);

        return [
            'shipping_fee' => $ttc,
            'shipping_fee_ht' => $ht,
            'zone' => $zone,
            'label' => self::label($origin) . ' → ' . self::label($destination),
            'weight_kg' => $weightKg,
        ];
    }

    public static function normalizeTerritory(?string $value): string
    {
        $v = mb_strtolower(trim((string) $value));

        return match (true) {
            str_contains($v, 'réunion'), str_contains($v, 'reunion'), $v === '974' => 'reunion',
            str_contains($v, 'guyane'), $v === '973' => 'guyane',
            str_contains($v, 'martinique'), $v === '972' => 'martinique',
            str_contains($v, 'guadeloupe'), $v === '971' => 'guadeloupe',
            str_contains($v, 'mayotte'), $v === '976' => 'mayotte',
            str_contains($v, 'métropole'), str_contains($v, 'metropole'), $v === 'france' => 'metropole',
            str_contains($v, 'international') => 'international',
            default => 'metropole',
        };
    }

    public static function territoryFromPostalCode(?string $postalCode): string
    {
        $p = preg_replace('/\D/', '', (string) $postalCode);

        return match (true) {
            str_starts_with($p, '974') => 'reunion',
            str_starts_with($p, '973') => 'guyane',
            str_starts_with($p, '972') => 'martinique',
            str_starts_with($p, '971') => 'guadeloupe',
            str_starts_with($p, '976') => 'mayotte',
            strlen($p) === 5 => 'metropole',
            default => 'metropole',
        };
    }

    private static function zone(string $origin, string $destination): string
    {
        if ($destination === 'international') {
            return 'international_zone_2';
        }

        if ($destination === 'metropole') {
            return 'france';
        }

        if ($origin === $destination) {
            return 'intra_om';
        }

        $pair = [$origin, $destination];
        sort($pair);

        if (in_array(implode('-', $pair), ['mayotte-reunion', 'guadeloupe-martinique'], true)) {
            return 'outre_mer_proximite';
        }

        return 'outre_mer_eloigne';
    }

    private static function priceHt(string $zone, float $weight): float
    {
        $tables = [
            'intra_om' => [
                ['max' => 0.25, 'price' => 6.84],
                ['max' => 0.50, 'price' => 7.71],
                ['max' => 0.75, 'price' => 8.60],
                ['max' => 1.00, 'price' => 9.34],
                ['max' => 2.00, 'price' => 10.48],
                ['max' => 3.00, 'price' => 11.49],
                ['max' => 4.00, 'price' => 12.54],
                ['max' => 5.00, 'price' => 13.54],
                ['max' => 10.00, 'price' => 18.17],
                ['max' => 15.00, 'price' => 22.73],
                ['max' => 20.00, 'price' => 27.62],
                ['max' => 25.00, 'price' => 32.19],
                ['max' => 30.00, 'price' => 37.05],
            ],
            'france' => [
                ['max' => 0.50, 'price' => 10.86],
                ['max' => 1.00, 'price' => 16.46],
                ['max' => 2.00, 'price' => 22.43],
                ['max' => 3.00, 'price' => 28.41],
                ['max' => 4.00, 'price' => 34.39],
                ['max' => 5.00, 'price' => 38.61],
                ['max' => 10.00, 'price' => 69.45],
                ['max' => 15.00, 'price' => 97.31],
                ['max' => 20.00, 'price' => 125.22],
                ['max' => 25.00, 'price' => 156.65],
                ['max' => 30.00, 'price' => 189.29],
            ],
            'outre_mer_proximite' => [
                ['max' => 0.50, 'price' => 7.37],
                ['max' => 1.00, 'price' => 8.87],
                ['max' => 2.00, 'price' => 10.36],
                ['max' => 3.00, 'price' => 11.88],
                ['max' => 4.00, 'price' => 12.77],
                ['max' => 5.00, 'price' => 14.21],
                ['max' => 10.00, 'price' => 21.36],
                ['max' => 15.00, 'price' => 24.55],
                ['max' => 20.00, 'price' => 25.94],
                ['max' => 25.00, 'price' => 28.37],
                ['max' => 30.00, 'price' => 30.82],
            ],
            'outre_mer_eloigne' => [
                ['max' => 0.50, 'price' => 12.89],
                ['max' => 1.00, 'price' => 20.03],
                ['max' => 2.00, 'price' => 35.60],
                ['max' => 3.00, 'price' => 51.14],
                ['max' => 4.00, 'price' => 63.81],
                ['max' => 5.00, 'price' => 78.69],
                ['max' => 10.00, 'price' => 153.12],
                ['max' => 15.00, 'price' => 235.13],
                ['max' => 20.00, 'price' => 299.01],
                ['max' => 25.00, 'price' => 373.16],
                ['max' => 30.00, 'price' => 447.28],
            ],
            'international_zone_2' => [
                ['max' => 0.50, 'price' => 34.70],
                ['max' => 1.00, 'price' => 40.95],
                ['max' => 2.00, 'price' => 53.62],
                ['max' => 3.00, 'price' => 66.27],
                ['max' => 4.00, 'price' => 78.93],
                ['max' => 5.00, 'price' => 91.57],
                ['max' => 10.00, 'price' => 154.78],
                ['max' => 15.00, 'price' => 217.33],
                ['max' => 20.00, 'price' => 279.95],
                ['max' => 25.00, 'price' => 342.49],
                ['max' => 30.00, 'price' => 405.09],
            ],
        ];

        foreach ($tables[$zone] ?? [] as $row) {
            if ($weight <= (float) $row['max']) {
                return (float) $row['price'];
            }
        }

        throw new RuntimeException('Tarif Colissimo introuvable.');
    }

    private static function label(string $territory): string
    {
        return match ($territory) {
            'reunion' => 'La Réunion',
            'guyane' => 'Guyane',
            'martinique' => 'Martinique',
            'guadeloupe' => 'Guadeloupe',
            'mayotte' => 'Mayotte',
            'metropole' => 'France métropolitaine',
            default => 'International',
        };
    }
}

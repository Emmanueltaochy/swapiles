<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class ColissimoPickupService
{
    public function search(array $data): array
    {
        $endpoint = 'https://ws.colissimo.fr/pointretrait-ws-cxf/PointRetraitServiceWS/2.0/findRDVPointRetraitAcheminement';

        $payload = [
            'apikey' => env('COLISSIMO_API_KEY'),
            'address' => $data['address'] ?? '',
            'zipCode' => $data['zip_code'] ?? '',
            'city' => $data['city'] ?? '',
            'countryCode' => $data['country_code'] ?? 'FR',
            'weight' => (string) max(1, (int) round(((float) ($data['weight_kg'] ?? 0.5)) * 1000)),
            'shippingDate' => now()->addDays(2)->format('d/m/Y'),
            'filterRelay' => '1',
            'requestId' => 'SWAP-' . now()->timestamp,
            'lang' => 'FR',
        ];

        $response = Http::timeout(25)
            ->acceptJson()
            ->get($endpoint, $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Erreur Colissimo Point Retrait HTTP ' . $response->status()
                . ' | body=' . substr($response->body(), 0, 1500)
            );
        }

        $body = trim($response->body());
        $json = $response->json();

        if (!$json && str_starts_with($body, '<')) {
            $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NOCDATA);
            $json = json_decode(json_encode($xml), true);
        }

        $points = $json['listPointRetraitAcheminement']
            ?? $json['pointRetraitAcheminement']
            ?? $json['listePointRetraitAcheminement']
            ?? $json['points']
            ?? [];

        if (isset($points['identifiant'])) {
            $points = [$points];
        }

        return collect($points)->map(fn ($p) => [
            'id' => $p['identifiant'] ?? null,
            'name' => $p['nom'] ?? $p['libelle'] ?? 'Point retrait',
            'address' => trim(($p['adresse1'] ?? '') . ' ' . ($p['adresse2'] ?? '') . ' ' . ($p['adresse3'] ?? '')),
            'postal_code' => $p['codePostal'] ?? '',
            'city' => $p['localite'] ?? $p['commune'] ?? '',
            'country' => $p['codePays'] ?? 'FR',
            'type' => $p['typeDePoint'] ?? '',
            'distance' => $p['distanceEnMetre'] ?? null,
            'lat' => $p['coordGeolocalisationLatitude'] ?? null,
            'lng' => $p['coordGeolocalisationLongitude'] ?? null,
        ])->filter(fn ($p) => $p['id'])->values()->all();
    }
}

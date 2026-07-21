<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ColissimoService
{
    public function generateLabel(Transaction $transaction): array
    {
        $endpoint = rtrim(config('colissimo.endpoint'), '/') . '/generateLabel';

        $letter = [
            'service' => [
                'productCode' => 'DOM',
                'depositDate' => now()->format('Y-m-d'),
                'orderNumber' => 'SWAP-' . $transaction->id,
            ],
            'parcel' => [
                'weight' => 0.5,
            ],
            'sender' => [
                'address' => [
                    'companyName' => config('colissimo.sender.name'),
                    'city' => config('colissimo.sender.city'),
                    'zipCode' => config('colissimo.sender.zip'),
                    'countryCode' => config('colissimo.sender.country'),
                ],
            ],
            'addressee' => [
                'address' => [
                    'lastName' => $transaction->buyer->name ?? 'Client Swapiles',
                    'city' => $transaction->buyer->city ?? 'Saint-Pierre',
                    'zipCode' => $transaction->buyer->postal_code ?? '97410',
                    'countryCode' => 'FR',
                ],
            ],
        ];

        $response = Http::timeout(60)
            ->withOptions([
                'multipart' => [
                    ['name' => 'contractNumber', 'contents' => config('colissimo.contract_number')],
                    ['name' => 'password', 'contents' => config('colissimo.password')],
                    ['name' => 'outputFormat', 'contents' => json_encode(['x' => 0, 'y' => 0, 'outputPrintingType' => 'PDF_A4_300dpi'])],
                    ['name' => 'letter', 'contents' => json_encode($letter)],
                ],
            ])
            ->post($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException('Erreur Colissimo HTTP ' . $response->status() . ' : ' . substr($response->body(), 0, 500));
        }

        $body = $response->body();

        $path = 'colissimo/labels/transaction-' . $transaction->id . '.pdf';
        Storage::disk('local')->put($path, $body);

        preg_match('/[A-Z0-9]{13}/', $body, $match);
        $tracking = $match[0] ?? null;

        return [
            'label_path' => $path,
            'tracking_number' => $tracking,
        ];
    }
}

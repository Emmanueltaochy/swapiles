<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TestColissimoLabel extends Command
{
    protected $signature = 'colissimo:test-label';
    protected $description = 'Teste la génération réelle d’une étiquette Colissimo';

    public function handle(): int
    {
        $endpoint = 'https://ws.colissimo.fr/sls-ws/SlsServiceWSRest/2.0/generateLabel';

        $payload = [
            'contractNumber' => env('COLISSIMO_ACCOUNT_NUMBER'),
            'password' => env('COLISSIMO_PASSWORD'),
            'outputFormat' => [
                'x' => 0,
                'y' => 0,
                'outputPrintingType' => 'PDF_A4_300dpi',
            ],
            'letter' => [
                'service' => [
                    'productCode' => 'COL',
                    'depositDate' => now()->addDay()->format('Y-m-d'),
                    'orderNumber' => 'SWAP-TEST-' . time(),
                ],
                'parcel' => [
                    'weight' => 0.5,
                ],
                'sender' => [
                    'address' => [
                        'companyName' => 'Swapiles',
                        'line2' => '10 Rue de Rivoli',
                        'countryCode' => 'FR',
                        'city' => 'Paris',
                        'zipCode' => '75001',
                    ],
                ],
                'addressee' => [
                    'address' => [
                        'lastName' => 'TEST',
                        'firstName' => 'Client',
                        'line2' => '10 Rue de Rivoli',
                        'countryCode' => 'FR',
                        'city' => 'Paris',
                        'zipCode' => '75001',
                    ],
                ],
            ],
        ];

        $response = Http::timeout(60)
            ->acceptJson()
            ->asJson()
            ->post($endpoint, $payload);

        $this->info('HTTP status : ' . $response->status());

        $body = $response->body();

        if (str_contains($body, '%PDF')) {
            $start = strpos($body, '%PDF');
            $pdf = substr($body, $start);

            Storage::disk('local')->put('colissimo/test-label.pdf', $pdf);

            $this->info('PDF généré : storage/app/colissimo/test-label.pdf');
            return self::SUCCESS;
        }

        $this->error('Pas de PDF détecté. Retour Colissimo :');
        $this->line(substr($body, 0, 3000));

        return self::FAILURE;
    }
}

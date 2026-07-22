<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ColissimoLabelService
{
    public function generateForTransaction(Transaction $transaction): array
    {
        $transaction->loadMissing(['seller', 'buyer', 'listing']);

        $seller = $transaction->seller;
        $buyer = $transaction->buyer;

        // Adresse de livraison : on prend en priorité l'adresse saisie par
        // l'acheteur au moment du paiement (sur la transaction), sinon on
        // retombe sur l'adresse de son profil.
        $buyerLine1 = $transaction->shipping_address_line1 ?: $buyer?->address_line1;
        $buyerLine2 = $transaction->shipping_address_line2 ?: $buyer?->address_line2;
        $buyerCity = $transaction->shipping_city ?: $buyer?->city;
        $buyerZip = $transaction->shipping_postal_code ?: $buyer?->postal_code;
        $buyerCountry = $transaction->shipping_country ?: ($buyer?->country_code ?: 'FR');
        $buyerName = $transaction->buyer_full_name ?: ($buyer?->name ?: 'Client');
        $buyerPhone = $transaction->buyer_phone ?: $buyer?->phone;

        if (! $seller || empty($seller->address_line1) || empty($seller->postal_code) || empty($seller->city)) {
            throw new RuntimeException("Adresse du vendeur manquante. Complétez votre adresse d'expédition dans votre profil avant de générer le bordereau.");
        }

        if (empty($buyerLine1) || empty($buyerZip) || empty($buyerCity)) {
            throw new RuntimeException("Adresse de livraison de l'acheteur manquante sur cette commande.");
        }

        $endpoint = 'https://ws.colissimo.fr/sls-ws/SlsServiceWSRest/2.0/generateLabel';

        // Le numéro de contrat peut être renseigné sous l'un ou l'autre nom.
        $contractNumber = trim((string) (env('COLISSIMO_ACCOUNT_NUMBER') ?: env('COLISSIMO_CONTRACT_NUMBER')));
        $password = trim((string) env('COLISSIMO_PASSWORD'));

        if ($contractNumber === '' || $password === '') {
            throw new RuntimeException("Identifiants Colissimo non configurés sur le serveur (COLISSIMO_ACCOUNT_NUMBER ou COLISSIMO_CONTRACT_NUMBER + COLISSIMO_PASSWORD dans le .env).");
        }

        $payload = [
            'contractNumber' => $contractNumber,
            'password' => $password,
            'outputFormat' => [
                'x' => 0,
                'y' => 0,
                'outputPrintingType' => 'PDF_A4_300dpi',
            ],
            'letter' => [
                'service' => [
                    'productCode' => 'COL',
                    'depositDate' => now()->addDay()->format('Y-m-d'),
                    'orderNumber' => 'SWAP-' . $transaction->id,
                    // Montant des frais de transport en centimes (requis pour la douane DOM).
                    'totalAmount' => (int) round(((float) $transaction->shipping_fee) * 100),
                ],
                'parcel' => [
                    'weight' => (float) ($transaction->listing->weight_kg ?? 0.5),
                ],
                'sender' => [
                    'address' => [
                        'companyName' => $seller->name ?: 'Vendeur Swapiles',
                        'line2' => $seller->address_line1,
                        'line3' => $seller->address_line2,
                        'countryCode' => $seller->country_code ?: 'FR',
                        'city' => $seller->city,
                        'zipCode' => $seller->postal_code,
                        'phoneNumber' => $seller->phone,
                    ],
                ],
                'addressee' => [
                    'address' => [
                        'lastName' => $buyerName,
                        'firstName' => '',
                        'line2' => $buyerLine1,
                        'line3' => $buyerLine2,
                        'countryCode' => $buyerCountry,
                        'city' => $buyerCity,
                        'zipCode' => $buyerZip,
                        'phoneNumber' => $buyerPhone,
                    ],
                ],
            ],
        ];

        // Envois vers/depuis les DOM (97x : Réunion, Antilles, Guyane, Mayotte) :
        // Colissimo exige une déclaration douanière du contenu, sinon erreur 30500
        // « Le contenu du colis n'a pas été transmis ».
        $senderZip = (string) $seller->postal_code;
        $isOverseas = str_starts_with($senderZip, '97') || str_starts_with((string) $buyerZip, '97');

        if ($isOverseas) {
            $itemValue = max(
                1,
                (float) $transaction->amount
                - (float) $transaction->buyer_protection_fee
                - (float) $transaction->shipping_fee
            );

            $payload['letter']['customsDeclarations'] = [
                'includeCustomsDeclarations' => 1,
                'numberOfCopies' => 1,
                'contents' => [
                    'article' => [
                        [
                            'description' => mb_substr($transaction->listing->title ?? "Article d'occasion", 0, 64),
                            'quantity' => 1,
                            'weight' => (float) ($transaction->listing->weight_kg ?? 0.5),
                            'value' => round($itemValue, 2),
                            'hsCode' => '630900',
                            'originCountry' => 'FR',
                            'currency' => 'EUR',
                        ],
                    ],
                    'category' => [
                        'value' => 3,
                    ],
                ],
            ];
        }

        $response = Http::timeout(60)->acceptJson()->asJson()->post($endpoint, $payload);

        $body = $response->body();

        if (! $response->successful()) {
            throw new RuntimeException('Erreur Colissimo HTTP ' . $response->status() . ' : ' . substr($body, 0, 1500));
        }

        if (! str_contains($body, '%PDF')) {
            throw new RuntimeException('Colissimo n’a pas retourné de PDF : ' . substr($body, 0, 1500));
        }

        $pdf = substr($body, strpos($body, '%PDF'));
        $path = 'colissimo/labels/transaction-' . $transaction->id . '.pdf';

        Storage::disk('local')->put($path, $pdf);

        $transaction->forceFill([
            'carrier' => 'Colissimo',
            'colissimo_label_path' => $path,
        ])->save();

        if (preg_match('/N(?:um|°)?\\s*(?:de\\s*)?colis[^0-9A-Z]*([0-9A-Z ]{10,25})/i', $body, $m)) {
            $tracking = trim(preg_replace('/\s+/', '', $m[1]));
            $transaction->update([
                'carrier' => 'Colissimo',
                'tracking_number' => $tracking,
                'tracking_url' => 'https://www.laposte.fr/outils/suivre-vos-envois?code=' . $tracking,
            ]);
        }

        return [
            'path' => $path,
        ];
    }

    public function generateLabel(\App\Models\Transaction $transaction): array
    {
        return $this->generateForTransaction($transaction);
    }

}

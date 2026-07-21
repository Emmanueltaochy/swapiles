<?php

return [
    'contract_number' => env('COLISSIMO_CONTRACT_NUMBER'),
    'password' => env('COLISSIMO_PASSWORD'),
    'endpoint' => env('COLISSIMO_ENDPOINT', 'https://ws.colissimo.fr/sls-ws/SlsServiceWSRest/2.0'),

    'sender' => [
        'name' => env('COLISSIMO_SENDER_NAME', "Swap'Îles"),
        'city' => env('COLISSIMO_SENDER_CITY', 'Saint-Pierre'),
        'zip' => env('COLISSIMO_SENDER_ZIP', '97410'),
        'country' => env('COLISSIMO_SENDER_COUNTRY', 'FR'),
    ],
];

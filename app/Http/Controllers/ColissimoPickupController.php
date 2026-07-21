<?php

namespace App\Http\Controllers;

use App\Services\ColissimoPickupService;
use Illuminate\Http\Request;

class ColissimoPickupController extends Controller
{
    public function search(Request $request, ColissimoPickupService $service)
    {
        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:200'],
            'zip_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:120'],
            'country_code' => ['nullable', 'string', 'max:2'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.01', 'max:30'],
        ]);

        try {
            return response()->json([
                'ok' => true,
                'points' => $service->search($data),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'Impossible de récupérer les points retrait Colissimo pour le moment.',
            ], 422);
        }
    }
}

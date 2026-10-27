<?php

namespace App\Http\Controllers;

use App\Support\IaDepot;
use App\Support\RecherchePhoto;
use App\Support\TerritoireContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/** 📷 dans la barre de recherche : la photo devient une recherche classique. */
class RecherchePhotoController extends Controller
{
    /** Recherches par photo par jour : membre connecté, ou adresse IP pour un visiteur. */
    public const LIMITE_JOUR = 15;

    public function chercher(Request $request): JsonResponse
    {
        abort_unless(IaDepot::disponible(), 404);

        $request->validate(['photo' => ['required', 'image', 'max:8192']], [
            'photo.required' => 'Prends ou choisis une photo.',
            'photo.image' => 'Ce fichier n’est pas une photo.',
        ]);

        $cle = 'recherche-photo:' . ($request->user()?->id ?? $request->ip());
        if (RateLimiter::tooManyAttempts($cle, self::LIMITE_JOUR)) {
            return response()->json(['message' => 'Tu as fait beaucoup de recherches par photo aujourd’hui. Réessaie demain, ou tape ta recherche.'], 429);
        }
        RateLimiter::hit($cle, 86400);

        try {
            $r = RecherchePhoto::analyser($request->file('photo'), $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage() . ' Essaie une autre photo, ou tape ta recherche.'], 422);
        }

        $parametres = array_filter([
            'q' => $r['q'],
            'category' => $r['categorie'],
            'territoire' => TerritoireContext::resolve($request),
            'photo' => 1,
        ]);

        return response()->json(['url' => route('search', $parametres), 'q' => $r['q']]);
    }
}

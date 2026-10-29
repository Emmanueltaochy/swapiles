<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\IaDepot;
use App\Support\PrixReperes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** « ✨ Remplir avec l'IA » sur la page de dépôt : les photos -> propositions de champs, et prix repères Swap'Îles. */
class IaDepotController extends Controller
{
    public function remplir(Request $request): JsonResponse
    {
        abort_unless(IaDepot::disponible(), 404);

        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:' . (int) config('ia_depot.photos_max', 4)],
            'images.*' => ['image', 'max:8192'],
        ], [
            'images.required' => 'Ajoute au moins une photo.',
            'images.*.image' => 'Ce fichier n’est pas une photo.',
        ]);

        $membre = $request->user();
        if (IaDepot::resteAujourdhui($membre) <= 0) {
            return response()->json(['message' => 'Tu as utilisé l’IA ' . (int) config('ia_depot.limite_par_jour', 20) . ' fois aujourd’hui. Réessaie demain, ou remplis la fiche à la main.'], 429);
        }

        try {
            $champs = IaDepot::analyser($membre, $request->file('images'), $request->string('jeton')->limit(64, '')->toString() ?: null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage() . ' Remplis la fiche à la main.'], 422);
        }

        return response()->json([
            'champs' => $champs,
            'reste' => IaDepot::resteAujourdhui($membre),
            // Champ ajouté : une page de dépôt ouverte avant la mise à jour l'ignore simplement.
            'reperes' => PrixReperes::pour($champs['categorie_1'], $champs['categorie_2'], $champs['categorie_3'], $this->ile($request)),
        ]);
    }

    /** Prix repères quand le vendeur choisit (ou change) lui-même la catégorie. */
    public function prixReperes(Request $request): JsonResponse
    {
        return response()->json([
            'reperes' => PrixReperes::pour(
                $request->string('c1')->limit(60, '')->toString(),
                $request->string('c2')->limit(60, '')->toString(),
                $request->string('c3')->limit(60, '')->toString(),
                $this->ile($request),
            ),
        ]);
    }

    private function ile(Request $request): ?string
    {
        $ile = $request->string('territoire')->limit(40, '')->toString();

        return $ile !== '' ? $ile : ($request->user()?->territoire ?: null);
    }
}

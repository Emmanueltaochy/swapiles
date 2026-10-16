<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Stories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StoryController extends Controller
{
    /** Les nouveautés d'un dressing, pour la visionneuse plein écran. */
    public function show(Request $request, User $user): JsonResponse
    {
        $moi = $request->user();
        abort_if($user->is_banned, 404);
        abort_if($moi && ($moi->hasBlocked($user) || $moi->isBlockedBy($user)), 404);

        $articles = Stories::articles($user)->map(function ($annonce) {
            $image = $annonce->images->sortBy('order')->first();

            return [
                'id' => $annonce->id,
                'titre' => $annonce->title,
                'prix' => (float) $annonce->price > 0 ? number_format((float) $annonce->price, 0, ',', ' ') . ' €' : 'Gratuit',
                'image' => $image ? \App\Support\ImageUrl::absolute($image->url) : null,
                'url' => route('listings.show', $annonce),
                'quand' => $annonce->created_at?->locale('fr')->diffForHumans(),
            ];
        })->values();

        return response()->json([
            'vendeur' => [
                'id' => $user->id,
                'nom' => $user->name,
                'avatar' => $user->avatar,
                'profil' => route('profiles.show', $user),
            ],
            'articles' => $articles,
        ]);
    }

    /** Rond gris : le membre a vu ces nouveautés. */
    public function vu(Request $request, User $user): Response
    {
        Stories::marquerVu($request->user(), $user);

        return response()->noContent();
    }
}

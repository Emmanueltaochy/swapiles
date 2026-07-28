<?php

namespace App\Http\Controllers;

use App\Models\SearchAlert;
use Illuminate\Http\Request;

/**
 * Point 17b : capture d'une alerte « préviens-moi » depuis une recherche sans
 * résultat. On enregistre l'intérêt (terme + e-mail). AUCUN e-mail n'est
 * envoyé maintenant : l'alerte partira plus tard, quand une annonce
 * correspondante sera publiée et une fois la délivrabilité (DKIM) réglée.
 */
class SearchAlertController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'term' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:191'],
            'territoire' => ['nullable', 'string', 'max:100'],
        ], [
            'email.required' => 'Indique ton e-mail pour être prévenu·e.',
            'email.email' => 'Cet e-mail ne semble pas valide.',
        ]);

        $raw = trim($data['term']);
        $term = SearchAlert::normalize($raw);

        if ($term === '') {
            return back()->withErrors(['term' => 'Terme de recherche manquant.']);
        }

        // Un même e-mail ne s'abonne qu'une fois par terme (unique term+email).
        SearchAlert::updateOrCreate(
            ['term' => $term, 'email' => mb_strtolower(trim($data['email']))],
            [
                'raw_term' => mb_substr($raw, 0, 255),
                'user_id' => optional($request->user())->id,
                'visitor_id' => $request->attributes->get('swp_vid') ?? $request->cookie('swp_vid'),
                'territoire' => $data['territoire'] ?? null,
            ]
        );

        return back()->with('alert_saved', $raw);
    }
}

<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileSettingsController extends Controller
{
    public function edit()
    {
        $user = Auth::user();

        return view('account.profile.edit', [
            'user' => $user,
            'relayPoints' => config('features.relay_points')
                ? \App\Models\RelayPoint::activeForTerritoire($user->territoire)
                : collect(),
            'selectedRelayIds' => $user->acceptedRelayPoints()->pluck('relay_points.id')->all(),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'territoire' => ['nullable', 'string', 'max:80'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'avatar' => ['nullable', 'image', 'max:5120'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = Storage::url($path);
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['country_code'] = $data['country_code'] ?? 'FR';

        // Le code postal DOM-TOM détermine l'île de façon certaine (971 = Guadeloupe,
        // 972 = Martinique, 973 = Guyane, 974 = La Réunion, 976 = Mayotte). Quand il
        // correspond à une de nos îles, il fait foi sur le territoire (évite les
        // profils marqués « La Réunion » par défaut alors que l'adresse est ailleurs).
        $territoireFromPostal = \App\Support\DomTomGeo::territoireFromPostal($data['postal_code'] ?? null);
        if ($territoireFromPostal) {
            $data['territoire'] = $territoireFromPostal;
        }

        $user->forceFill($data)->save();

        // L'île du profil vient de changer : on recale le sélecteur d'île pour
        // que la navigation ne reste pas sur l'ancienne.
        \App\Support\TerritoireContext::rememberForUser($user);

        // Points relais acceptés par défaut : on ne garde que des relais actifs
        // situés sur l'île du vendeur (pas de dépôt sur une autre île).
        if (config('features.relay_points')) {
            $ids = collect($request->input('relay_point_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->all();

            $valid = \App\Models\RelayPoint::query()
                ->active()
                ->where('territoire', $user->territoire)
                ->whereIn('id', $ids ?: [0])
                ->pluck('id')
                ->all();

            $user->acceptedRelayPoints()->sync($valid);
        }

        $status = 'Profil mis à jour.';
        if ($territoireFromPostal && ($request->input('territoire') !== $territoireFromPostal)) {
            $status = 'Profil mis à jour. Votre île a été ajustée sur « ' . $territoireFromPostal . ' » d’après votre code postal.';
        }

        return back()->with('status', $status);
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\ReunionCommunes;
use Filament\Pages\Page;

class UsersMap extends Page
{
    protected static ?string $navigationLabel = 'Carte des membres';
    protected static ?string $title = 'Carte des membres — La Réunion';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.users-map';

    public function getViewData(): array
    {
        $city = trim((string) request()->query('city', ''));

        $base = User::query()
            ->where('territoire', 'La Réunion')
            ->whereNotNull('city')
            ->where('city', '!=', '');

        // Liste des villes disponibles (pour le filtre).
        $cities = (clone $base)
            ->select('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->filter()
            ->values();

        $query = clone $base;
        if ($city !== '') {
            $query->where('city', $city);
        }

        $users = $query->get(['id', 'name', 'email', 'city', 'postal_code', 'address_line1']);

        $points = [];
        $unlocated = 0;

        foreach ($users as $u) {
            $coords = ReunionCommunes::coords($u->city);

            if (! $coords) {
                $unlocated++;
                continue;
            }

            // Décalage déterministe (basé sur l'id) pour éviter la superposition.
            $jLat = ((($u->id * 7) % 21) - 10) * 0.0016;
            $jLng = ((($u->id * 13) % 21) - 10) * 0.0016;

            $points[] = [
                'lat' => round($coords[0] + $jLat, 5),
                'lng' => round($coords[1] + $jLng, 5),
                'name' => $u->name ?: 'Membre',
                'email' => $u->email,
                'city' => $u->city,
                'address' => trim(($u->address_line1 ? $u->address_line1 . ', ' : '') . ($u->postal_code ? $u->postal_code . ' ' : '') . $u->city),
                'url' => UserResource::getUrl('view', ['record' => $u->id]),
            ];
        }

        return [
            'points' => $points,
            'cities' => $cities,
            'selectedCity' => $city,
            'totalMembers' => $base->count(),
            'shownCount' => count($points),
            'unlocated' => $unlocated,
            'center' => ReunionCommunes::CENTER,
        ];
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Support\DomTomGeo;
use App\Support\Territoires;
use Filament\Pages\Page;

class UsersMap extends Page
{
    protected static ?string $navigationLabel = 'Carte des membres';
    protected static ?string $title = 'Carte des membres';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.users-map';

    public function getViewData(): array
    {
        $territoires = DomTomGeo::territoires();

        // Nombre de membres géolocalisables par territoire (pour le sélecteur).
        $counts = User::query()
            ->whereIn('territoire', $territoires)
            ->whereNotNull('city')->where('city', '!=', '')
            ->selectRaw('territoire, COUNT(*) as total')
            ->groupBy('territoire')
            ->pluck('total', 'territoire');

        // Territoire sélectionné : celui demandé, sinon celui qui a le plus de membres.
        $territoire = (string) request()->query('territoire', '');
        if (! in_array($territoire, $territoires, true)) {
            $territoire = $counts->sortDesc()->keys()->first() ?: 'La Réunion';
        }

        $city = trim((string) request()->query('city', ''));

        $base = User::query()
            ->where('territoire', $territoire)
            ->whereNotNull('city')->where('city', '!=', '');

        $cities = (clone $base)->select('city')->distinct()->orderBy('city')->pluck('city')->filter()->values();

        $query = clone $base;
        if ($city !== '') {
            $query->where('city', $city);
        }

        $users = $query->get(['id', 'name', 'email', 'city', 'postal_code', 'address_line1', 'territoire']);

        [$centerLat, $centerLng, $zoom] = DomTomGeo::center($territoire);

        $points = [];
        $approx = 0;

        foreach ($users as $u) {
            $coords = DomTomGeo::coords($territoire, $u->city);
            $isApprox = false;

            if (! $coords) {
                // Ville non reconnue : on place au centre de l'île (approximatif).
                $coords = [$centerLat, $centerLng];
                $isApprox = true;
                $approx++;
            }

            $jLat = ((($u->id * 7) % 21) - 10) * 0.0016;
            $jLng = ((($u->id * 13) % 21) - 10) * 0.0016;

            $points[] = [
                'lat' => round($coords[0] + $jLat, 5),
                'lng' => round($coords[1] + $jLng, 5),
                'name' => $u->name ?: 'Membre',
                'email' => $u->email,
                'city' => $u->city,
                'approx' => $isApprox,
                'url' => UserResource::getUrl('view', ['record' => $u->id]),
            ];
        }

        // Libellés territoires (avec « La ») + compteurs pour le sélecteur.
        $territoireTabs = [];
        foreach ($territoires as $t) {
            $territoireTabs[$t] = [
                'display' => Territoires::display($t),
                'count' => (int) ($counts[$t] ?? 0),
            ];
        }

        return [
            'points' => $points,
            'cities' => $cities,
            'selectedCity' => $city,
            'territoire' => $territoire,
            'territoireDisplay' => Territoires::display($territoire),
            'territoireTabs' => $territoireTabs,
            'totalMembers' => (int) ($counts[$territoire] ?? 0),
            'shownCount' => count($points),
            'approx' => $approx,
            'center' => [$centerLat, $centerLng],
            'zoom' => $zoom,
        ];
    }
}

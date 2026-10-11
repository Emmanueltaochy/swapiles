<?php

use App\Support\Categories;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recherches sans résultat : on retire les noms de catégories.
 *
 * « femme », « high-tech », « jardin-bricolage »… n'étaient pas des
 * recherches tapées : le fil d'Ariane des annonces, lu par Google, pointait
 * vers une recherche de texte au lieu de la catégorie. Ces milliers de lignes
 * noyaient les vraies demandes (« veste étanche », « lit enfant »…) dans
 * l'administration. Le lien est corrigé ; on nettoie l'historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('search_no_results')
            ->select('term')
            ->distinct()
            ->pluck('term')
            ->filter(fn ($terme) => Categories::niveau1DepuisTexte($terme) !== null)
            ->each(fn ($terme) => DB::table('search_no_results')->where('term', $terme)->delete());
    }

    public function down(): void
    {
        // Rien à restaurer : c'étaient des visites mal enregistrées.
    }
};

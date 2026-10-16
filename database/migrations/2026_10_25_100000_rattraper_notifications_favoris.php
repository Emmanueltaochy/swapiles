<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les favoris n'apparaissaient pas dans l'appli (seulement par e-mail) :
 * on rattrape ceux des 3 derniers jours, une notification par vendeur.
 * Insertion directe : aucune notification ne sonne sur les téléphones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('favorites') || ! Schema::hasTable('notifications')) {
            return;
        }

        $depuis = now()->subDays(3);

        $parVendeur = DB::table('favorites')
            ->join('listings', 'listings.id', '=', 'favorites.listing_id')
            ->where('favorites.created_at', '>=', $depuis)
            ->whereColumn('favorites.user_id', '!=', 'listings.user_id')
            ->selectRaw('listings.user_id as vendeur_id, COUNT(*) as total, MAX(favorites.created_at) as dernier')
            ->groupBy('listings.user_id')
            ->get();

        foreach ($parVendeur as $ligne) {
            $dejaPrevenu = DB::table('notifications')
                ->where('user_id', $ligne->vendeur_id)
                ->where('type', 'favorite_added')
                ->where('created_at', '>=', $depuis)
                ->exists();
            if ($dejaPrevenu) {
                continue;
            }

            $total = (int) $ligne->total;
            DB::table('notifications')->insert([
                'user_id' => $ligne->vendeur_id,
                'type' => 'favorite_added',
                'title' => 'Vos annonces plaisent ❤️',
                'message' => $total > 1
                    ? $total . ' personnes ont ajouté vos annonces à leurs favoris ces derniers jours.'
                    : 'Quelqu’un a ajouté une de vos annonces à ses favoris.',
                'url' => '/mon-compte',
                'created_at' => $ligne->dernier,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Rien : les notifications restent consultables.
    }
};

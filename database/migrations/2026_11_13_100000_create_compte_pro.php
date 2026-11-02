<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compte professionnel (voir App\Support\ComptePro) : identité légale, mode de
 * remise choisi par le pro, commission, autorisation de lancer des lives ; et
 * côté vente : vendeur pro au moment de l'achat, code de retrait en boutique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pro_raison_sociale', 160)->nullable();
            $table->string('pro_siret', 14)->nullable();
            $table->string('pro_adresse', 255)->nullable();
            $table->string('pro_telephone', 40)->nullable();
            // Commission Swap'Îles propre à ce pro (sinon le taux par défaut).
            $table->decimal('pro_commission_taux', 5, 4)->nullable();
            $table->boolean('pro_retrait_boutique')->default(true);
            $table->boolean('pro_livraison')->default(false);
            $table->decimal('pro_livraison_prix', 8, 2)->nullable();
            $table->string('pro_livraison_delai', 80)->nullable();
            $table->text('pro_retours')->nullable();
            // Le mode pro ne s'applique qu'une fois validé par l'admin.
            $table->timestamp('pro_valide_at')->nullable();
            // Peut lancer un live (les autres membres peuvent seulement regarder).
            $table->boolean('live_autorise')->default(false);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('vendeur_pro')->default(false);
            $table->string('code_retrait', 12)->nullable();
            $table->timestamp('retrait_valide_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['vendeur_pro', 'code_retrait', 'retrait_valide_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pro_raison_sociale', 'pro_siret', 'pro_adresse', 'pro_telephone', 'pro_commission_taux', 'pro_retrait_boutique',
                'pro_livraison', 'pro_livraison_prix', 'pro_livraison_delai', 'pro_retours', 'pro_valide_at', 'live_autorise']);
        });
    }
};

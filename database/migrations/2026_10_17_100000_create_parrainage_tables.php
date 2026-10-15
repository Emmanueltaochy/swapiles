<?php

use App\Support\CodeParrainage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Programme de parrainage : codes, parrainages, portefeuille de crédits,
 * journal des événements, ouvertures d'invitation (lien différé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('code_parrainage', 20)->nullable()->unique();
            // Ancien code (avant personnalisation) : les liens déjà partagés marchent encore.
            $table->string('code_parrainage_ancien', 20)->nullable()->index();
            $table->timestamp('code_personnalise_at')->nullable();
            $table->foreignId('parrain_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('parraine_at')->nullable();
            $table->timestamp('ambassadeur_at')->nullable();
            $table->timestamp('a_mettre_en_avant_at')->nullable();
        });

        Schema::create('parrainages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parrain_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('filleul_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('code', 20);
            // lien | saisie | appariement
            $table->string('source', 12)->default('saisie');
            // inscrit | achat_en_cours | valide | refuse | annule
            $table->string('statut', 16)->default('inscrit')->index();
            // Une commande ne peut jamais déclencher deux récompenses.
            $table->foreignId('transaction_id')->nullable()->unique()->constrained('transactions')->nullOnDelete();
            $table->unsignedInteger('montant_centimes')->default(0);
            $table->string('motif_refus', 40)->nullable();
            $table->timestamp('valide_at')->nullable()->index();
            $table->timestamp('annule_at')->nullable();
            $table->string('annule_raison', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('portefeuille_mouvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // credit_parrainage | restitution (lots) ; utilisation | expiration | annulation (débits)
            $table->string('type', 20)->index();
            $table->integer('montant_centimes');
            // Lots : ce qu'il reste à dépenser.
            $table->unsignedInteger('restant_centimes')->default(0);
            $table->timestamp('expire_at')->nullable()->index();
            $table->unsignedBigInteger('lot_id')->nullable()->index();
            $table->foreignId('parrainage_id')->nullable()->constrained('parrainages')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->timestamp('restitue_at')->nullable();
            $table->timestamp('rappel_envoye_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('parrainage_evenements', function (Blueprint $table) {
            $table->id();
            // referral_link_shared | referral_link_opened | referral_signup |
            // referral_first_purchase | referral_reward_granted | referral_reward_rejected | …
            $table->string('evenement', 40)->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parrainage_id')->nullable()->constrained('parrainages')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('canal', 20)->nullable();
            $table->string('motif', 40)->nullable()->index();
            $table->json('donnees')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        // Lien différé : purgé après 7 jours (RGPD).
        Schema::create('invitations_ouvertes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->foreignId('parrain_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_hash', 64)->index();
            $table->string('plateforme', 10)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Protection acheteur offerte (parrainage) : montant non facturé.
            $table->decimal('protection_offerte', 10, 2)->default(0);
            // Crédit du portefeuille déduit du total (payé par Swap'Îles).
            $table->decimal('credit_utilise', 10, 2)->default(0);
            // Empreinte de la carte (Stripe) : même carte = même personne.
            $table->string('carte_empreinte', 64)->nullable()->index();
        });

        // Un code pour chaque compte existant.
        DB::table('users')->whereNull('code_parrainage')->orderBy('id')
            ->chunkById(500, function ($membres) {
                foreach ($membres as $m) {
                    DB::table('users')->where('id', $m->id)
                        ->update(['code_parrainage' => CodeParrainage::generer((string) $m->name, $m->territoire)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['carte_empreinte']);
            $table->dropColumn(['protection_offerte', 'credit_utilise', 'carte_empreinte']);
        });
        Schema::dropIfExists('invitations_ouvertes');
        Schema::dropIfExists('parrainage_evenements');
        Schema::dropIfExists('portefeuille_mouvements');
        Schema::dropIfExists('parrainages');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parrain_id');
            $table->dropUnique(['code_parrainage']);
            $table->dropIndex(['code_parrainage_ancien']);
            $table->dropColumn(['code_parrainage', 'code_parrainage_ancien', 'code_personnalise_at', 'parraine_at', 'ambassadeur_at', 'a_mettre_en_avant_at']);
        });
    }
};

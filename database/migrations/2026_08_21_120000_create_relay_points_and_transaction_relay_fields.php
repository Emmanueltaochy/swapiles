<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point relais (pilote La Réunion) : des commerçants partenaires gardent le
 * colis pour l'acheteur. Réservé au paiement CB (l'argent est bloqué avant le
 * dépôt : le commerçant ne manipule jamais d'argent). Frais relais ajoutés au
 * total, répartis commerçant / plateforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('relay_points')) {
            Schema::create('relay_points', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('territoire');
                $table->string('address')->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->string('city')->nullable();
                $table->string('contact_name')->nullable();
                $table->string('contact_phone', 40)->nullable();
                $table->string('opening_hours')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['territoire', 'is_active']);
            });
        }

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'relay_point_id')) {
                $table->foreignId('relay_point_id')->nullable()->after('hand_delivery_location')
                    ->constrained('relay_points')->nullOnDelete();
            }
            if (! Schema::hasColumn('transactions', 'relay_fee')) {
                $table->decimal('relay_fee', 8, 2)->default(0)->after('relay_point_id');
            }
            if (! Schema::hasColumn('transactions', 'relay_merchant_fee')) {
                $table->decimal('relay_merchant_fee', 8, 2)->default(0)->after('relay_fee');
            }
            if (! Schema::hasColumn('transactions', 'relay_status')) {
                $table->string('relay_status')->nullable()->after('relay_merchant_fee');
            }
            if (! Schema::hasColumn('transactions', 'relay_pickup_code')) {
                $table->string('relay_pickup_code', 12)->nullable()->after('relay_status');
            }
            if (! Schema::hasColumn('transactions', 'relay_deposited_at')) {
                $table->timestamp('relay_deposited_at')->nullable()->after('relay_pickup_code');
            }
            if (! Schema::hasColumn('transactions', 'relay_collected_at')) {
                $table->timestamp('relay_collected_at')->nullable()->after('relay_deposited_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            foreach ([
                'relay_deposited_at', 'relay_collected_at', 'relay_pickup_code',
                'relay_status', 'relay_merchant_fee', 'relay_fee',
            ] as $col) {
                if (Schema::hasColumn('transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
            if (Schema::hasColumn('transactions', 'relay_point_id')) {
                $table->dropConstrainedForeignId('relay_point_id');
            }
        });

        Schema::dropIfExists('relay_points');
    }
};

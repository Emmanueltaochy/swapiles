<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi des campagnes newsletter (à la Mailchimp) : envois, ouvertures (pixel),
 * clics (redirection), pour calculer taux d'ouverture, taux de clic, CTR, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('newsletter_campaigns')) {
            Schema::create('newsletter_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('subject');
                $table->string('format', 10)->default('html');
                $table->string('audience', 40)->nullable();
                $table->unsignedInteger('recipients_count')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        if (! Schema::hasTable('newsletter_recipients')) {
            Schema::create('newsletter_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('newsletter_campaigns')->cascadeOnDelete();
                $table->string('email');
                $table->string('token', 64)->unique();
                $table->timestamp('opened_at')->nullable();
                $table->timestamp('first_clicked_at')->nullable();
                $table->unsignedInteger('open_count')->default(0);
                $table->unsignedInteger('click_count')->default(0);
                $table->index(['campaign_id', 'opened_at']);
            });
        }

        if (! Schema::hasTable('newsletter_events')) {
            Schema::create('newsletter_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('newsletter_campaigns')->cascadeOnDelete();
                $table->unsignedBigInteger('recipient_id')->nullable();
                $table->string('type', 10); // open | click
                $table->text('url')->nullable();
                $table->string('ip_address', 80)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['campaign_id', 'type']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_events');
        Schema::dropIfExists('newsletter_recipients');
        Schema::dropIfExists('newsletter_campaigns');
    }
};

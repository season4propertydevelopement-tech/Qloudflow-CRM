<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meta_campaign_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('meta_campaigns')->cascadeOnDelete();
            $table->string('meta_lead_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('raw_phone')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('city')->nullable();
            $table->string('platform')->nullable();
            $table->string('lead_status')->default('CREATED');
            
            // Email automation status
            $table->string('email_status')->default('not_sent')->index(); // not_sent, queued, sending, sent, failed
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();

            // WhatsApp automation status
            $table->string('whatsapp_status')->default('not_sent')->index(); // not_sent, queued, sending, sent, failed
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->text('whatsapp_error')->nullable();

            // Dynamic fields from Google Sheets
            $table->json('custom_fields')->nullable();
            $table->json('raw_data')->nullable();

            $table->timestamps();

            // Composite index for fast campaign lead querying and status filtering
            $table->index(['campaign_id', 'email_status']);
            $table->index(['campaign_id', 'whatsapp_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_campaign_leads');
    }
};

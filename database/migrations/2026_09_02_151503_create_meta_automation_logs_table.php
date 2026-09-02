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
        Schema::create('meta_automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('meta_campaigns')->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('meta_campaign_leads')->cascadeOnDelete();
            $table->string('channel'); // 'email' or 'whatsapp'
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('message_preview')->nullable();
            $table->string('status'); // 'sent', 'failed'
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_automation_logs');
    }
};

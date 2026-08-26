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
        Schema::create('voice_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('caller_name')->nullable();
            $table->string('caller_phone')->nullable();
            $table->string('persona_id')->default('universal');
            $table->string('persona_name')->default('Avni');
            $table->string('duration')->default('00:00');
            $table->string('status')->default('completed'); // completed, missed, interrupted
            $table->text('call_summary')->nullable();
            $table->unsignedTinyInteger('lead_score')->default(70);
            $table->string('lead_stage')->default('Warm Prospect');
            $table->string('sentiment')->default('Positive');
            $table->json('key_entities')->nullable();
            $table->text('whatsapp_followup_message')->nullable();
            $table->json('transcript')->nullable();
            $table->string('model_used')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voice_calls');
    }
};

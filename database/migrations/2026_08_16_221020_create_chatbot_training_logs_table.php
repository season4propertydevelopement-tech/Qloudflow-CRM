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
        Schema::create('chatbot_training_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->onDelete('cascade');
            $table->text('incoming_message');
            $table->string('detected_intent')->nullable();
            $table->float('confidence')->nullable();
            $table->json('selected_knowledge')->nullable();
            $table->text('generated_response')->nullable();
            $table->string('provider')->nullable();
            $table->boolean('was_accepted')->default(true);
            $table->boolean('was_corrected')->default(false);
            $table->text('administrator_correction')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chatbot_training_logs');
    }
};

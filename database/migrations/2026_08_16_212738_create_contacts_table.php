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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->unique();
            $table->string('whatsapp_id')->nullable();
            $table->string('profile_image_url')->nullable();
            $table->timestamp('first_message_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->boolean('opt_in_status')->default(true);
            $table->text('notes')->nullable();
            $table->string('tags')->nullable();
            $table->boolean('chatbot_enabled')->default(true);
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->boolean('human_handoff')->default(false);
            $table->string('handoff_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

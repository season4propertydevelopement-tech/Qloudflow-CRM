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
        Schema::create('meta_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('sheet_url')->nullable();
            $table->string('status')->default('active'); // active, paused, completed, archived
            $table->unsignedInteger('total_leads')->default(0);
            $table->unsignedInteger('emails_sent')->default(0);
            $table->unsignedInteger('emails_failed')->default(0);
            $table->unsignedInteger('whatsapp_sent')->default(0);
            $table->unsignedInteger('whatsapp_failed')->default(0);
            $table->json('headers')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_campaigns');
    }
};

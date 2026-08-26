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
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source_type');
            $table->string('source_url')->nullable();
            $table->string('file_path')->nullable();
            $table->longText('raw_content')->nullable();
            $table->string('language')->default('en');
            $table->boolean('is_active')->default(true);
            $table->string('index_status')->default('pending');
            $table->timestamp('last_indexed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_sources');
    }
};

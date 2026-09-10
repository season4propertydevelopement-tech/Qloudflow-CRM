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
        Schema::table('meta_campaigns', function (Blueprint $table) {
            $table->boolean('auto_welcome_enabled')->default(false)->after('status');
            $table->boolean('auto_welcome_whatsapp')->default(false)->after('auto_welcome_enabled');
            $table->foreignId('auto_welcome_whatsapp_template_id')->nullable()->after('auto_welcome_whatsapp')->constrained('meta_templates')->nullOnDelete();
            $table->text('auto_welcome_whatsapp_message')->nullable()->after('auto_welcome_whatsapp_template_id');
            $table->text('auto_welcome_whatsapp_media_url')->nullable()->after('auto_welcome_whatsapp_message');

            $table->boolean('auto_welcome_email')->default(false)->after('auto_welcome_whatsapp_media_url');
            $table->foreignId('auto_welcome_email_template_id')->nullable()->after('auto_welcome_email')->constrained('meta_templates')->nullOnDelete();
            $table->string('auto_welcome_email_subject')->nullable()->after('auto_welcome_email_template_id');
            $table->text('auto_welcome_email_body')->nullable()->after('auto_welcome_email_subject');

            $table->string('webhook_token', 64)->nullable()->unique()->after('headers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meta_campaigns', function (Blueprint $table) {
            $table->dropForeign(['auto_welcome_whatsapp_template_id']);
            $table->dropForeign(['auto_welcome_email_template_id']);
            $table->dropColumn([
                'auto_welcome_enabled',
                'auto_welcome_whatsapp',
                'auto_welcome_whatsapp_template_id',
                'auto_welcome_whatsapp_message',
                'auto_welcome_whatsapp_media_url',
                'auto_welcome_email',
                'auto_welcome_email_template_id',
                'auto_welcome_email_subject',
                'auto_welcome_email_body',
                'webhook_token',
            ]);
        });
    }
};

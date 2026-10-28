<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->enum('source', ['whatsapp', 'trial'])->default('whatsapp')->after('wa_message_id');
            $table->char('trial_key', 36)->nullable()->after('source');
            $table->index(['business_id', 'trial_key']);
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'trial_key']);
            $table->dropColumn(['source', 'trial_key']);
        });
    }
};

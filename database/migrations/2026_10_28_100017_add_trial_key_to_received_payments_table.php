<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_payments', function (Blueprint $table) {
            $table->char('trial_key', 36)->nullable()->after('idempotency_key');
            $table->index(['business_id', 'trial_key']);
        });
    }

    public function down(): void
    {
        Schema::table('received_payments', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'trial_key']);
            $table->dropColumn('trial_key');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->index(['business_id', 'operation_code'], 'verifications_business_operation_idx');
            $table->index(['business_id', 'media_sha256'], 'verifications_business_sha_idx');
            $table->index(
                ['business_id', 'security_code', 'amount', 'paid_at'],
                'verifications_business_capture_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->dropIndex('verifications_business_operation_idx');
            $table->dropIndex('verifications_business_sha_idx');
            $table->dropIndex('verifications_business_capture_idx');
        });
    }
};

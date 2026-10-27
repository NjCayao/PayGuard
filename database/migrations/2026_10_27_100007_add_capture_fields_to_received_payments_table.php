<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('received_payments', function (Blueprint $table) {
            $table->string('source_package', 100)->after('wallet');
            $table->char('security_code', 3)->nullable()->after('payer_name');
            $table->string('operation_code', 40)->nullable()->after('security_code');
        });
    }

    public function down(): void
    {
        Schema::table('received_payments', function (Blueprint $table) {
            $table->dropColumn(['source_package', 'security_code', 'operation_code']);
        });
    }
};

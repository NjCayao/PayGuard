<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->string('recipient_name', 120)->nullable()->after('amount');
        });

        DB::table('verifications')->whereNotNull('payer_name')->update([
            'recipient_name' => DB::raw('payer_name'),
        ]);

        Schema::table('verifications', function (Blueprint $table) {
            $table->dropColumn('payer_name');
        });
    }

    public function down(): void
    {
        Schema::table('verifications', function (Blueprint $table) {
            $table->string('payer_name', 120)->nullable()->after('amount');
        });

        DB::table('verifications')->whereNotNull('recipient_name')->update([
            'payer_name' => DB::raw('recipient_name'),
        ]);

        Schema::table('verifications', function (Blueprint $table) {
            $table->dropColumn('recipient_name');
        });
    }
};

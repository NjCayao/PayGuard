<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alter(['queued', 'sent', 'delivered', 'read', 'failed', 'simulated']);
    }

    public function down(): void
    {
        DB::table('owner_alerts')->where('status', 'simulated')->update(['status' => 'failed']);

        $this->alter(['queued', 'sent', 'delivered', 'read', 'failed']);
    }

    private function alter(array $statuses): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = implode(', ', array_map(fn ($status) => "'{$status}'", $statuses));

            DB::statement("ALTER TABLE owner_alerts MODIFY COLUMN status ENUM({$list}) NOT NULL DEFAULT 'queued'");

            return;
        }

        Schema::disableForeignKeyConstraints();

        Schema::table('owner_alerts', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'created_at']);
            $table->dropUnique(['wa_message_id']);
        });

        Schema::rename('owner_alerts', 'owner_alerts_previous');

        Schema::create('owner_alerts', function (Blueprint $table) use ($statuses) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verification_id')->nullable()->constrained()->nullOnDelete();
            $table->string('to', 20);
            $table->string('template', 80);
            $table->string('reason', 255);
            $table->string('wa_message_id', 100)->nullable()->unique();
            $table->enum('status', $statuses)->default('queued');
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
        });

        $columns = [
            'id', 'business_id', 'verification_id', 'to', 'template', 'reason',
            'wa_message_id', 'status', 'error', 'created_at', 'updated_at',
        ];

        DB::table('owner_alerts')->insertUsing(
            $columns,
            DB::table('owner_alerts_previous')->select($columns)
        );

        Schema::drop('owner_alerts_previous');

        Schema::enableForeignKeyConstraints();
    }
};

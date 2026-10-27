<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('received_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->enum('wallet', ['yape', 'plin']);
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('payer_name', 120)->nullable();
            $table->dateTime('received_at');
            $table->text('raw_text');
            $table->boolean('parsed')->default(false);
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index(['business_id', 'amount', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('received_payments');
    }
};

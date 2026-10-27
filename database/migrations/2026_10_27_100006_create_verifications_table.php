<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_id')->constrained()->cascadeOnDelete();
            $table->string('wa_message_id', 100)->unique();
            $table->enum('input_type', ['image', 'audio', 'text']);
            $table->string('media_path', 255)->nullable();
            $table->char('media_sha256', 64)->nullable();
            $table->text('transcript')->nullable();
            $table->json('extracted')->nullable();
            $table->enum('wallet', ['yape', 'plin'])->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('payer_name', 120)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('operation_code', 40)->nullable();
            $table->json('edit_signals')->nullable();
            $table->enum('status', ['processing', 'pending', 'verified', 'not_found', 'mismatch', 'reused', 'error'])->default('processing');
            $table->string('reason', 255)->nullable();
            $table->foreignId('received_payment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->dateTime('recheck_until')->nullable();
            $table->integer('ai_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verifications');
    }
};

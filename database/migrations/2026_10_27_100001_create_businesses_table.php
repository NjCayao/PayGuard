<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('owner_whatsapp', 20);
            $table->smallInteger('match_window_minutes')->default(10);
            $table->smallInteger('recheck_minutes')->default(2);
            $table->string('timezone', 40)->default('America/Lima');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};

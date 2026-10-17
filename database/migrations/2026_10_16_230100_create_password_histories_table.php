<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('password_hash');
            $table->timestamps();
            $table->index(['usuario_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');
    }
};

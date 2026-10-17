<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('pedidos');
            $table->uuid('idempotency_key')->unique();
            $table->string('requisicao_hash', 64);
            $table->string('order_id')->nullable()->unique();
            $table->string('status')->default('criando');
            $table->text('instrucoes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_pagamentos');
    }
};

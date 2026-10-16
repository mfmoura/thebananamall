<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table): void {
            $table->id();
            $table->string('nome');
            $table->decimal('preco', 12, 2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('formas_pagamento', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nome');
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('pedidos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('forma_pagamento_id')->constrained('formas_pagamento')->restrictOnDelete();
            $table->string('status', 40)->default('aguardando_pagamento');
            $table->timestamp('data');
            $table->string('idempotency_key', 36);
            $table->timestamps();
            $table->unique(['cliente_id', 'idempotency_key']);
        });

        Schema::create('pedido_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('codigo')->constrained('produtos')->restrictOnDelete();
            $table->decimal('preco', 12, 2);
            $table->decimal('quantidade', 12, 3);
            $table->date('data');
            $table->timestamps();
            $table->unique(['pedido_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_itens');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('formas_pagamento');
        Schema::dropIfExists('produtos');
    }
};

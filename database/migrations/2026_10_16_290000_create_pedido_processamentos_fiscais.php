<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', fn (Blueprint $table) => $table->jsonb('configuracao_fiscal')->nullable());
        Schema::table('pedido_itens', fn (Blueprint $table) => $table->jsonb('configuracao_fiscal')->nullable());
        Schema::create('pedido_processamentos_fiscais', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('nota_fiscal_id')->nullable()->constrained('notas_fiscais')->restrictOnDelete();
            $table->uuid('chave')->unique();
            $table->string('etapa', 40)->default('aguardando_pagamento');
            $table->text('dados_cliente')->nullable();
            $table->text('dados_emissao')->nullable();
            $table->text('ultimo_erro')->nullable();
            $table->timestamp('proxima_tentativa')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('sequencias_fiscais', function (Blueprint $table): void {
            $table->id();
            $table->string('cnpj', 14);
            $table->string('ambiente', 20);
            $table->unsignedInteger('serie');
            $table->unsignedBigInteger('ultimo_numero')->default(0);
            $table->unique(['cnpj', 'ambiente', 'serie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_processamentos_fiscais');
        Schema::dropIfExists('sequencias_fiscais');
        Schema::table('produtos', fn (Blueprint $table) => $table->dropColumn('configuracao_fiscal'));
        Schema::table('pedido_itens', fn (Blueprint $table) => $table->dropColumn('configuracao_fiscal'));
    }
};

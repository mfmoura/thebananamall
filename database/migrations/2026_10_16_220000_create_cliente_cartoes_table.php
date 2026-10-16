<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_cartoes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('apelido', 80);
            $table->string('tipo', 20);
            $table->string('status', 20)->default('ativo');
            $table->date('validade');
            $table->string('bandeira', 40);
            $table->char('ultimos_quatro', 4);
            $table->string('gateway', 80);
            $table->text('referencia_cliente_gateway');
            $table->text('referencia_cartao_gateway');
            $table->timestamps();

            $table->index(['cliente_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_cartoes');
    }
};

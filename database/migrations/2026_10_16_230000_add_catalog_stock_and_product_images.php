<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table): void {
            $table->string('codigo', 80)->nullable()->after('id');
            $table->string('categoria', 40)->default('outro')->after('nome');
        });

        DB::table('produtos')->whereNull('codigo')->orderBy('id')->chunkById(500, function ($produtos): void {
            foreach ($produtos as $produto) {
                DB::table('produtos')->where('id', $produto->id)->update([
                    'codigo' => 'LCB-'.str_pad((string) $produto->id, 6, '0', STR_PAD_LEFT),
                ]);
            }
        });

        Schema::table('produtos', function (Blueprint $table): void {
            $table->string('codigo', 80)->nullable(false)->change();
            $table->unique('codigo');
        });

        Schema::create('notas_estoque', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_id')->nullable()->unique()->constrained('pedidos')->restrictOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('referencia', 120)->nullable();
            $table->text('observacao')->nullable();
            $table->timestamps();
        });

        Schema::create('produto_movimentacoes', function (Blueprint $table): void {
            $table->id();
            $table->string('direcao', 10);
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->foreignId('nota_id')->constrained('notas_estoque')->restrictOnDelete();
            $table->decimal('quantidade', 12, 3);
            $table->timestamp('data');
            $table->unsignedBigInteger('estorno_de_id')->nullable()->unique();
            $table->foreign('estorno_de_id')->references('id')->on('produto_movimentacoes')->restrictOnDelete();
            $table->timestamps();

            $table->index(['produto_id', 'data']);
            $table->unique(['nota_id', 'produto_id']);
        });

        Schema::create('produto_imagens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->string('path', 2048);
            $table->boolean('principal')->default(false);
            $table->timestamps();

            $table->index(['produto_id', 'principal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_imagens');
        Schema::dropIfExists('produto_movimentacoes');
        Schema::dropIfExists('notas_estoque');

        Schema::table('produtos', function (Blueprint $table): void {
            $table->dropUnique(['codigo']);
            $table->dropColumn(['codigo', 'categoria']);
        });
    }
};

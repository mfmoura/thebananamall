<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', fn (Blueprint $table) => $table->json('grade')->nullable());
        foreach (['produto_movimentacoes', 'pedido_itens', 'nota_fiscal_itens'] as $nome) {
            Schema::table($nome, fn (Blueprint $table) => $table->string('tamanho', 30)->nullable());
        }
        Schema::table('pedido_itens', fn (Blueprint $table) => $table->dropUnique(['pedido_id', 'codigo']));
        Schema::table('produto_movimentacoes', fn (Blueprint $table) => $table->dropUnique(['nota_id', 'produto_id']));
        DB::statement("CREATE UNIQUE INDEX pedido_itens_produto_tamanho_unique ON pedido_itens (pedido_id, codigo, COALESCE(tamanho, ''))");
        DB::statement("CREATE UNIQUE INDEX movimentacoes_produto_tamanho_unique ON produto_movimentacoes (nota_id, produto_id, COALESCE(tamanho, ''))");
    }

    public function down(): void
    {
        foreach (['produto_movimentacoes', 'pedido_itens', 'nota_fiscal_itens'] as $nome) {
            if (DB::table($nome)->whereNotNull('tamanho')->exists()) {
                throw new RuntimeException('A grade já possui histórico por tamanho; a reversão removeria essa identificação.');
            }
        }
        DB::statement('DROP INDEX pedido_itens_produto_tamanho_unique');
        DB::statement('DROP INDEX movimentacoes_produto_tamanho_unique');
        Schema::table('pedido_itens', fn (Blueprint $table) => $table->unique(['pedido_id', 'codigo']));
        Schema::table('produto_movimentacoes', fn (Blueprint $table) => $table->unique(['nota_id', 'produto_id']));
        foreach (['produto_movimentacoes', 'pedido_itens', 'nota_fiscal_itens'] as $nome) {
            Schema::table($nome, fn (Blueprint $table) => $table->dropColumn('tamanho'));
        }
        Schema::table('produtos', fn (Blueprint $table) => $table->dropColumn('grade'));
    }
};

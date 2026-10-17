<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table): void {
            $table->string('natureza_operacao', 20)->nullable();
            $table->foreignId('pedido_id')->nullable()->change();
        });

        DB::table('notas_fiscais')->update([
            'natureza_operacao' => DB::raw("CASE tipo_operacao WHEN 'entrada' THEN 'compra' ELSE 'venda' END"),
        ]);

        Schema::table('notas_fiscais', function (Blueprint $table): void {
            $table->string('natureza_operacao', 20)->nullable(false)->change();
        });

        Schema::table('notas_estoque', function (Blueprint $table): void {
            $table->foreignId('nota_fiscal_id')->nullable()->unique()
                ->constrained('notas_fiscais')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('notas_fiscais')->whereNull('pedido_id')->exists()) {
            throw new RuntimeException(
                'Nao e possivel reverter: existem notas fiscais de estoque sem pedido associado.',
            );
        }

        Schema::table('notas_estoque', function (Blueprint $table): void {
            $table->dropUnique(['nota_fiscal_id']);
            $table->dropConstrainedForeignId('nota_fiscal_id');
        });

        Schema::table('notas_fiscais', function (Blueprint $table): void {
            $table->dropColumn('natureza_operacao');
            $table->foreignId('pedido_id')->nullable(false)->change();
        });
    }
};

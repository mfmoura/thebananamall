<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_fiscais', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->restrictOnDelete();
            $table->string('provedor')->nullable();
            $table->string('referencia_provedor')->nullable();
            $table->string('chave_acesso', 60)->nullable()->unique();
            $table->string('numero')->nullable();
            $table->string('serie')->nullable();
            $table->string('status', 30)->default('pendente');
            $table->string('tipo_operacao', 20);
            $table->timestamp('data_emissao')->nullable();
            $table->timestamp('data_autorizacao')->nullable();
            $table->string('caminho_xml', 2048)->nullable();
            $table->string('caminho_danfe', 2048)->nullable();
            $table->string('codigo_rejeicao')->nullable();
            $table->text('motivo_rejeicao')->nullable();
            $table->decimal('total_produtos', 12, 2)->default(0);
            $table->decimal('total_frete', 12, 2)->default(0);
            $table->decimal('total_desconto', 12, 2)->default(0);
            $table->decimal('total_impostos', 12, 2)->default(0);
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['provedor', 'referencia_provedor']);
            $table->index(['pedido_id', 'status']);
        });

        Schema::create('nota_fiscal_itens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('nota_fiscal_id')->constrained('notas_fiscais')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->string('codigo_produto', 80);
            $table->string('descricao');
            $table->string('ncm', 8)->nullable();
            $table->string('cfop', 4)->nullable();
            $table->string('unidade_comercial', 10)->nullable();
            $table->decimal('quantidade', 12, 3);
            $table->decimal('valor_unitario', 12, 2);
            $table->decimal('valor_total', 12, 2);
            $table->string('cst_csosn', 4)->nullable();
            $table->decimal('base_icms', 12, 2)->nullable();
            $table->decimal('aliquota_icms', 8, 4)->nullable();
            $table->decimal('valor_icms', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['nota_fiscal_id', 'produto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_fiscal_itens');
        Schema::dropIfExists('notas_fiscais');
    }
};

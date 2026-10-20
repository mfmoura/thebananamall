<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table): void {
            $table->unsignedInteger('peso_gramas')->nullable();
            foreach (['altura_cm', 'largura_cm', 'comprimento_cm'] as $campo) {
                $table->decimal($campo, 8, 2)->nullable();
            }
            $table->boolean('embalagem_estimada')->default(false);
        });
        Schema::table('pedidos', function (Blueprint $table): void {
            $table->decimal('custo_frete', 12, 2)->default(0);
            $table->json('frete')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', fn (Blueprint $table) => $table->dropColumn(['custo_frete', 'frete']));
        Schema::table('produtos', fn (Blueprint $table) => $table->dropColumn(['peso_gramas', 'altura_cm', 'largura_cm', 'comprimento_cm', 'embalagem_estimada']));
    }
};

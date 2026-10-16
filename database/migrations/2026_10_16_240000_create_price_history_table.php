<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->decimal('preco', 12, 2);
            $table->timestamp('data');
            $table->timestamps();

            $table->index(['produto_id', 'data', 'id']);
        });

        DB::table('produtos')->orderBy('id')->chunkById(500, function ($produtos): void {
            foreach ($produtos as $produto) {
                DB::table('precos')->insert([
                    'produto_id' => $produto->id,
                    'preco' => $produto->preco,
                    'data' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::table('pedido_itens', function (Blueprint $table): void {
            $table->foreignId('preco_id')->nullable()->after('codigo')
                ->constrained('precos')->restrictOnDelete();
        });

        Schema::table('produtos', function (Blueprint $table): void {
            $table->dropColumn('preco');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table): void {
            $table->decimal('preco', 12, 2)->nullable();
        });

        DB::table('produtos')->orderBy('id')->chunkById(500, function ($produtos): void {
            foreach ($produtos as $produto) {
                $preco = DB::table('precos')->where('produto_id', $produto->id)
                    ->orderByDesc('data')->orderByDesc('id')->value('preco');
                DB::table('produtos')->where('id', $produto->id)->update(['preco' => $preco ?? 0]);
            }
        });

        Schema::table('produtos', function (Blueprint $table): void {
            $table->decimal('preco', 12, 2)->nullable(false)->change();
        });

        Schema::table('pedido_itens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('preco_id');
        });

        Schema::dropIfExists('precos');
    }
};

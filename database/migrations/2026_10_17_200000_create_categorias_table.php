<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table): void {
            $table->id();
            $table->string('nome_codigo', 40)->unique();
            $table->string('nome');
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (['bandeira' => 'Bandeira', 'botton' => 'Botton', 'camiseta' => 'Camiseta', 'outro' => 'Outro'] as $codigo => $nome) {
            DB::table('categorias')->insert([
                'nome_codigo' => $codigo, 'nome' => $nome,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('produtos', function (Blueprint $table): void {
            $table->foreign('categoria')->references('nome_codigo')->on('categorias')->restrictOnDelete()->restrictOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table): void {
            $table->dropForeign(['categoria']);
        });
        Schema::dropIfExists('categorias');
    }
};

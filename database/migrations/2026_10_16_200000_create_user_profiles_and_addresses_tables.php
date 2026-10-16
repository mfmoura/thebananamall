<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('administradores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nivel_de_acesso', 40);
            $table->timestamps();
        });

        Schema::create('logisticas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('nome');
            $table->string('webhook', 2048);
            $table->timestamps();
        });

        Schema::create('enderecos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->cascadeOnDelete();
            $table->string('logradouro');
            $table->string('numero', 30);
            $table->string('complemento')->nullable();
            $table->string('cidade');
            $table->char('estado', 2);
            $table->char('pais', 2);
            $table->string('cep', 20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enderecos');
        Schema::dropIfExists('logisticas');
        Schema::dropIfExists('administradores');
        Schema::dropIfExists('clientes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedido_pagamentos', function (Blueprint $table): void {
            $table->text('dados_envio')->nullable();
            $table->string('processamento')->default('pendente')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pedido_pagamentos', function (Blueprint $table): void {
            $table->dropIndex(['processamento']);
            $table->dropColumn(['dados_envio', 'processamento']);
        });
    }
};

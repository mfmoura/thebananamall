<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercado_pago_eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('chave', 64)->unique();
            $table->string('order_id');
            $table->string('request_id');
            $table->string('status')->default('pendente')->index();
            $table->timestamp('processado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_eventos');
    }
};

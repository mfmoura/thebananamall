<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table): void {
            $table->string('ambiente', 20)->nullable();
            $table->string('emitente_documento', 14)->nullable();
            $table->uuid('emissao_idempotency_key')->nullable()->unique();
            $table->string('emissao_hash', 64)->nullable();
            $table->text('emissao_payload')->nullable();
            $table->boolean('emissao_enviada')->default(false);
            $table->string('protocolo_autorizacao')->nullable();
            $table->unique(['provedor', 'emitente_documento', 'ambiente', 'serie', 'numero'], 'nf_identidade_fiscal_unique');
        });
    }

    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table): void {
            $table->dropUnique('nf_identidade_fiscal_unique');
            $table->dropUnique(['emissao_idempotency_key']);
            $table->dropColumn(['ambiente', 'emitente_documento', 'emissao_idempotency_key', 'emissao_hash', 'emissao_payload', 'emissao_enviada', 'protocolo_autorizacao']);
        });
    }
};

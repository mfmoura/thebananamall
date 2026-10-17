<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('administradores', function (Blueprint $table): void {
            $table->jsonb('paginas')->default('[]');
        });

        DB::table('administradores')->update([
            'paginas' => json_encode(['contabilidade', 'estoque']),
        ]);

        Schema::table('administradores', function (Blueprint $table): void {
            $table->dropColumn('nivel_de_acesso');
        });
    }

    public function down(): void
    {
        Schema::table('administradores', function (Blueprint $table): void {
            $table->dropColumn('paginas');
            $table->string('nivel_de_acesso', 40)->default('administrador');
        });
    }
};

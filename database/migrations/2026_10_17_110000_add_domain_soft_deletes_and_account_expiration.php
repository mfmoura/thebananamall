<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    private array $tabelas = ['users', 'clientes', 'administradores', 'logisticas', 'enderecos', 'cliente_cartoes', 'produtos', 'produto_imagens', 'produto_movimentacoes', 'precos', 'pedidos', 'pedido_itens', 'formas_pagamento', 'notas_estoque', 'notas_fiscais', 'nota_fiscal_itens', 'pedido_processamentos_fiscais', 'visitantes', 'password_histories'];

    public function up(): void
    {
        foreach ($this->tabelas as $tabela) {
            Schema::table($tabela, fn (Blueprint $table) => $table->softDeletes());
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('email_confirmation_expires_at')->nullable();
            $table->index(['email_validado', 'last_login_at', 'email_confirmation_expires_at'], 'users_confirmation_cleanup_index');
            $table->dropUnique('users_email_unique');
            $table->dropUnique('users_login_unique');
        });
        DB::statement('CREATE UNIQUE INDEX users_email_active_unique ON users (email) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX users_login_active_unique ON users (login) WHERE deleted_at IS NULL');
        DB::table('users')->orderBy('id')->chunkById(100, function ($usuarios): void {
            foreach ($usuarios as $usuario) {
                $dados = ['last_login_at' => DB::table('oauth_access_tokens')->where('user_id', $usuario->id)->max('created_at')];
                if (! $usuario->email_validado && ! DB::table('visitantes')->where('usuario_id', $usuario->id)->exists()) {
                    $dados['email_confirmation_expires_at'] = Carbon::parse($usuario->created_at)->addSeconds(max(1, (int) config('services.email_verification.ttl', 300)));
                }
                DB::table('users')->where('id', $usuario->id)->update($dados);
            }
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX users_email_active_unique');
        DB::statement('DROP INDEX users_login_active_unique');
        Schema::table('users', function (Blueprint $table): void {
            $table->unique('email');
            $table->unique('login');
            $table->dropIndex('users_confirmation_cleanup_index');
            $table->dropColumn(['last_login_at', 'email_confirmation_expires_at']);
        });
        foreach (array_reverse($this->tabelas) as $tabela) {
            Schema::table($tabela, fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};

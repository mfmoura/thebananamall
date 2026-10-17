<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoProcessamentoFiscal extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'pedido_processamentos_fiscais';

    protected $guarded = ['id'];

    protected $visible = ['status', 'mensagem', 'nota_fiscal_id'];

    protected $appends = ['status', 'mensagem'];

    protected function casts(): array
    {
        return ['dados_cliente' => 'encrypted:array', 'dados_emissao' => 'encrypted:array', 'proxima_tentativa' => 'datetime'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class)->withTrashed();
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class)->withTrashed();
    }

    public function getStatusAttribute(): string
    {
        return match ($this->etapa) {
            'concluido' => 'concluido',
            'falhou' => 'falhou',
            'cancelado' => 'cancelado',
            default => 'processando',
        };
    }

    public function getMensagemAttribute(): string
    {
        return match ($this->status) {
            'concluido' => 'Sua nota fiscal está disponível.',
            'falhou' => 'Estamos verificando o processamento dos seus dados.',
            'cancelado' => 'O processamento foi cancelado.',
            default => 'Seus dados estão sendo processados.',
        };
    }
}

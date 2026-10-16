<?php

namespace App\Models;

use App\Enums\StatusClienteCartao;
use App\Enums\TipoCartao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClienteCartao extends Model
{
    protected $table = 'cliente_cartoes';

    protected $fillable = [
        'cliente_id',
        'apelido',
        'tipo',
        'status',
        'validade',
        'bandeira',
        'ultimos_quatro',
        'gateway',
        'referencia_cliente_gateway',
        'referencia_cartao_gateway',
    ];

    protected $hidden = [
        'referencia_cliente_gateway',
        'referencia_cartao_gateway',
    ];

    protected $appends = ['numero_cartao'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCartao::class,
            'status' => StatusClienteCartao::class,
            'validade' => 'date:Y-m-d',
            'referencia_cliente_gateway' => 'encrypted',
            'referencia_cartao_gateway' => 'encrypted',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** Número mascarado para exibição; o PAN completo nunca é persistido. */
    public function getNumeroCartaoAttribute(): string
    {
        return '•••• •••• •••• '.($this->ultimos_quatro ?? '••••');
    }
}

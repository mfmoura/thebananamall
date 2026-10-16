<?php

namespace App\Models;

use App\Enums\DirecaoMovimentacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoMovimentacao extends Model
{
    protected $table = 'produto_movimentacoes';

    protected $fillable = [
        'direcao',
        'produto_id',
        'nota_id',
        'quantidade',
        'data',
        'estorno_de_id',
    ];

    protected function casts(): array
    {
        return [
            'direcao' => DirecaoMovimentacao::class,
            'quantidade' => 'decimal:3',
            'data' => 'datetime',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaEstoque::class, 'nota_id');
    }

    public function movimentoOriginal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'estorno_de_id');
    }
}

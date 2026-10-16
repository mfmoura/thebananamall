<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaEstoque extends Model
{
    protected $table = 'notas_estoque';

    protected $fillable = ['pedido_id', 'usuario_id', 'referencia', 'observacao'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(ProdutoMovimentacao::class, 'nota_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaEstoque extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'notas_estoque';

    protected $fillable = ['pedido_id', 'nota_fiscal_id', 'usuario_id', 'referencia', 'observacao', 'status'];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class)->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class)->withTrashed();
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class, 'nota_fiscal_id')->withTrashed();
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(ProdutoMovimentacao::class, 'nota_id');
    }
}

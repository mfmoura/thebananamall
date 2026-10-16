<?php

namespace App\Models;

use App\Enums\StatusNotaFiscal;
use App\Enums\TipoOperacaoNotaFiscal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotaFiscal extends Model
{
    protected $table = 'notas_fiscais';

    protected $fillable = [
        'pedido_id',
        'provedor',
        'referencia_provedor',
        'chave_acesso',
        'numero',
        'serie',
        'status',
        'tipo_operacao',
        'data_emissao',
        'data_autorizacao',
        'caminho_xml',
        'caminho_danfe',
        'codigo_rejeicao',
        'motivo_rejeicao',
        'total_produtos',
        'total_frete',
        'total_desconto',
        'total_impostos',
        'valor_total',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusNotaFiscal::class,
            'tipo_operacao' => TipoOperacaoNotaFiscal::class,
            'data_emissao' => 'datetime',
            'data_autorizacao' => 'datetime',
            'total_produtos' => 'decimal:2',
            'total_frete' => 'decimal:2',
            'total_desconto' => 'decimal:2',
            'total_impostos' => 'decimal:2',
            'valor_total' => 'decimal:2',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NotaFiscalItem::class);
    }

    public function estaAutorizada(): bool
    {
        return $this->status === StatusNotaFiscal::Autorizada;
    }

    public function addItem(int $produtoId, NotaFiscalItem $item): NotaFiscalItem
    {
        $item->produto_id = $produtoId;

        $this->itens()->save($item);

        return $item;
    }
}

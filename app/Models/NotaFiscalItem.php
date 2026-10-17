<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaFiscalItem extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'nota_fiscal_itens';

    protected $fillable = [
        'produto_id',
        'codigo_produto',
        'descricao',
        'ncm',
        'cfop',
        'unidade_comercial',
        'quantidade',
        'valor_unitario',
        'valor_total',
        'cst_csosn',
        'base_icms',
        'aliquota_icms',
        'valor_icms',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'valor_unitario' => 'decimal:2',
            'valor_total' => 'decimal:2',
            'base_icms' => 'decimal:2',
            'aliquota_icms' => 'decimal:4',
            'valor_icms' => 'decimal:2',
        ];
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class)->withTrashed();
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class)->withTrashed();
    }
}

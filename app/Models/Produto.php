<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Produto extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'produtos';

    protected $fillable = ['grade', 'codigo', 'nome', 'descricao', 'categoria', 'ativo', 'configuracao_fiscal', 'peso_gramas', 'altura_cm', 'largura_cm', 'comprimento_cm', 'embalagem_estimada'];

    protected $appends = ['preco'];

    protected $hidden = ['precoAtual'];

    protected function casts(): array
    {
        return [
            'configuracao_fiscal' => 'array',
            'ativo' => 'boolean',
            'grade' => 'array',
            'peso_gramas' => 'integer',
            'altura_cm' => 'decimal:2',
            'largura_cm' => 'decimal:2',
            'comprimento_cm' => 'decimal:2',
            'embalagem_estimada' => 'boolean',
        ];
    }

    public function itensPedido(): HasMany
    {
        return $this->hasMany(PedidoItem::class, 'codigo');
    }

    public function categoriaCadastro(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria', 'nome_codigo')->withTrashed();
    }

    public function imagens(): HasMany
    {
        return $this->hasMany(ProdutoImagem::class);
    }

    public function precos(): HasMany
    {
        return $this->hasMany(Preco::class);
    }

    public function itensNotaFiscal(): HasMany
    {
        return $this->hasMany(NotaFiscalItem::class);
    }

    public function precoAtual(): HasOne
    {
        return $this->hasOne(Preco::class)->ofMany(['data' => 'max', 'id' => 'max']);
    }

    public function getPrecoAttribute(): ?string
    {
        $preco = $this->relationLoaded('precoAtual')
            ? $this->getRelation('precoAtual')
            : $this->precoAtual()->first();

        return $preco?->preco;
    }

    public function movimentacoes(): HasMany
    {
        return $this->hasMany(ProdutoMovimentacao::class);
    }
}

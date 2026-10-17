<?php

namespace App\Models;

use App\Enums\CategoriaProduto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Produto extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'produtos';

    protected $fillable = ['codigo', 'nome', 'descricao', 'categoria', 'ativo', 'configuracao_fiscal'];

    protected $appends = ['preco'];

    protected $hidden = ['precoAtual'];

    protected function casts(): array
    {
        return [
            'configuracao_fiscal' => 'array',
            'categoria' => CategoriaProduto::class,
            'ativo' => 'boolean',
        ];
    }

    public function itensPedido(): HasMany
    {
        return $this->hasMany(PedidoItem::class, 'codigo');
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

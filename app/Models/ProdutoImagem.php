<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdutoImagem extends Model
{
    protected $table = 'produto_imagens';

    protected $fillable = ['produto_id', 'path', 'principal'];

    protected function casts(): array
    {
        return ['principal' => 'boolean'];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Categoria extends Model
{
    use SoftDeletes;

    protected $fillable = ['nome_codigo', 'nome'];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class, 'categoria', 'nome_codigo');
    }
}

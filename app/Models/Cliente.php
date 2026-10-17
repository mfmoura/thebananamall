<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = ['usuario_id'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function cartoes(): HasMany
    {
        return $this->hasMany(ClienteCartao::class);
    }
}

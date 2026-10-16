<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Endereco extends Model
{
    protected $table = 'enderecos';

    protected $fillable = [
        'logradouro',
        'numero',
        'complemento',
        'cidade',
        'estado',
        'pais',
        'cep',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}

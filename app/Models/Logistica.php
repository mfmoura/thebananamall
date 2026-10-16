<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Logistica extends Model
{
    protected $table = 'logisticas';

    protected $fillable = ['usuario_id', 'nome', 'webhook'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}

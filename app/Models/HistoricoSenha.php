<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoricoSenha extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'password_histories';

    protected $fillable = ['usuario_id', 'password_hash'];

    protected $hidden = ['password_hash'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

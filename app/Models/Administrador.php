<?php

namespace App\Models;

use App\Enums\NivelAcesso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Administrador extends Model
{
    protected $table = 'administradores';

    protected $fillable = ['usuario_id', 'nivel_de_acesso'];

    protected function casts(): array
    {
        return ['nivel_de_acesso' => NivelAcesso::class];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}

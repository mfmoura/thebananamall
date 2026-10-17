<?php

namespace App\Models;

use App\Enums\NivelAcesso;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Administrador extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'administradores';

    protected $fillable = ['usuario_id', 'paginas'];

    protected function casts(): array
    {
        return ['paginas' => 'array'];
    }

    public function acessaPagina(NivelAcesso|string $pagina): bool
    {
        $valor = $pagina instanceof NivelAcesso ? $pagina->value : $pagina;

        return in_array($valor, $this->paginas ?? [], true);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}

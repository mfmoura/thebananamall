<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MercadoPagoEvento extends Model
{
    use SoftDeletes;

    protected $table = 'mercado_pago_eventos';

    protected $attributes = ['status' => 'pendente'];

    protected $fillable = ['chave', 'order_id', 'request_id', 'status', 'processado_em'];

    protected $hidden = ['chave'];

    protected function casts(): array
    {
        return ['processado_em' => 'datetime'];
    }
}

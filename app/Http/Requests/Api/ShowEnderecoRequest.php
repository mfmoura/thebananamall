<?php

namespace App\Http\Requests\Api;

use App\Models\Endereco;
use Illuminate\Foundation\Http\FormRequest;

class ShowEnderecoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $endereco = $this->route('endereco');

        return $endereco instanceof Endereco
            && ($this->user()?->id === $endereco->usuario_id
                || $this->user()?->administrador()->exists() === true);
    }
}

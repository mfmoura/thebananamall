<?php

namespace App\Http\Requests\Api;

use App\Models\Logistica;
use Illuminate\Validation\Rule;

class UpdateLogisticaRequest extends AdministradorRequest
{
    public function rules(): array
    {
        /** @var Logistica $logistica */
        $logistica = $this->route('logistica');

        return [
            'login' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'login')->withoutTrashed()->ignore($logistica->usuario_id)],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'max:15', 'confirmed'],
            'email' => ['prohibited'],
            'nome' => ['sometimes', 'required', 'string', 'max:255'],
            'webhook' => ['sometimes', 'required', 'url', 'max:2048'],
        ];
    }
}

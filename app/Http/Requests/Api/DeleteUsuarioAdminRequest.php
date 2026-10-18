<?php

namespace App\Http\Requests\Api;

class DeleteUsuarioAdminRequest extends MutateUsuarioAdminRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && ! $this->user()->is($this->route('usuario'));
    }
}

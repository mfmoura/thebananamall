<?php

namespace App\Http\Requests\Api;

use App\Enums\CategoriaProduto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProdutosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'categoria' => ['sometimes', 'nullable', Rule::enum(CategoriaProduto::class)],
            'busca' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}

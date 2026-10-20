<?php

namespace App\Http\Requests\Api\Concerns;

use Illuminate\Validation\Validator;

trait ValidaPaginasUsuarioAdmin
{
    public function after(): array
    {
        return [function (Validator $validator): void {
            $paginas = $this->input('paginas');
            if (! $this->user()?->administrador?->acessaPagina('manutencao')
                && is_array($paginas) && array_diff($paginas, $this->user()?->administrador?->paginas ?? [])) {
                $validator->errors()->add('paginas', 'Você não pode conceder permissões que não possui.');
            }
            $usuario = $this->route('usuario');
            $permissaoPropria = $this->user()?->administrador?->acessaPagina('usuarios') ? 'usuarios' : 'manutencao';
            if ($usuario && $this->user()?->is($usuario) && $paginas !== null
                && ! in_array($permissaoPropria, is_array($paginas) ? $paginas : [], true)) {
                $validator->errors()->add('paginas', 'Você não pode remover sua própria permissão de gestão de usuários.');
            }
        }];
    }
}

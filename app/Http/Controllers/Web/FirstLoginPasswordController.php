<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\FirstLoginPasswordRequest;
use App\Models\Usuario;
use App\Services\PoliticaSenhaService;
use Illuminate\Http\JsonResponse;

class FirstLoginPasswordController extends Controller
{
    public function update(
        FirstLoginPasswordRequest $request,
        PoliticaSenhaService $politicaSenhaService,
    ): JsonResponse {
        $dados = $request->validated();
        $usuario = Usuario::query()
            ->where('login', $dados['login'])
            ->orWhere('email', $dados['login'])
            ->first();

        if (
            $usuario === null
            || ! $politicaSenhaService->corresponde($dados['current_password'], $usuario->password)
            || ! $usuario->must_change_password
            || ! $usuario->email_validado
        ) {
            return response()->json(['message' => 'Não foi possível validar a troca inicial de senha.'], 403);
        }

        $politicaSenhaService->alterar($usuario, $dados['password']);
        $usuario->must_change_password = false;
        $usuario->save();
        $usuario->tokens()->delete();

        return response()->json([
            'message' => 'Senha atualizada. Faça login novamente para acessar a aplicação.',
            'requires_login' => true,
        ]);
    }
}

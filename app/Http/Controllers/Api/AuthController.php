<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $usuario = Usuario::query()
            ->where('login', $credentials['login'])
            ->orWhere('email', $credentials['login'])
            ->first();

        if (! $usuario || ! Hash::check($credentials['password'], $usuario->password)) {
            return response()->json([
                'message' => 'As credenciais informadas sao invalidas.',
            ], 401);
        }

        $token = $usuario->createToken($credentials['device_name'] ?? 'api-client');

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'usuario' => $usuario,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Sessao encerrada.',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\OAuthLoginRequest;
use App\Models\Usuario;
use App\Notifications\AlertaSenhaAntigaNotification;
use App\Services\PoliticaSenhaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class OAuthSessionController extends Controller
{
    public function destroy(\Illuminate\Http\Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Sessão encerrada.']);
    }

    public function store(OAuthLoginRequest $request, PoliticaSenhaService $politicaSenhaService): JsonResponse
    {
        $credentials = $request->validated();
        $usuario = Usuario::query()
            ->where('login', $credentials['login'])
            ->orWhere('email', $credentials['login'])
            ->orderByDesc('email_validado')->orderByDesc('id')
            ->first();

        if ($usuario === null) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        if (! $politicaSenhaService->corresponde($credentials['password'], $usuario->password)) {
            $senhaAntigaUsada = $usuario->historicoSenhas()
                ->latest('id')
                ->limit(4)
                ->get()
                ->contains(fn ($historico) => $politicaSenhaService->corresponde(
                    $credentials['password'],
                    $historico->password_hash,
                ));

            if ($senhaAntigaUsada && Cache::add(
                "alerta:senha-antiga:usuario:{$usuario->id}",
                true,
                now()->addMinutes(15),
            )) {
                try {
                    Notification::route('mail', $usuario->email)
                        ->notify(new AlertaSenhaAntigaNotification($usuario->id));
                } catch (Throwable $exception) {
                    Log::warning('Não foi possível enviar alerta de tentativa com senha antiga.', [
                        'usuario_id' => $usuario->id,
                        'exception' => $exception::class,
                    ]);
                }
            }

            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        if (! $usuario->email_validado) {
            return response()->json([
                'code' => 'email_confirmation_required',
                'message' => 'Confirme seu e-mail antes de entrar.',
            ], 403);
        }

        if ($usuario->must_change_password) {
            return response()->json([
                'code' => 'password_change_required',
                'message' => 'Troque sua senha antes de entrar.',
            ], 403);
        }

        if ($usuario->must_reauthenticate) {
            $usuario->must_reauthenticate = false;
            $usuario->save();
        }

        $usuario->forceFill(['last_login_at' => now()])->save();
        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();

        return response()->json([
            'continue_url' => $request->session()->pull('url.intended', url('/')),
        ]);
    }
}

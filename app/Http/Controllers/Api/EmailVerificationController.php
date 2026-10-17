<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ResendEmailVerificationRequest;
use App\Http\Requests\Api\VerifyEmailRequest;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;

class EmailVerificationController extends Controller
{
    public function verify(VerifyEmailRequest $request, EmailVerificationService $service): JsonResponse
    {
        $dados = $request->validated();
        $service->confirmar($dados['token'], $dados['password'] ?? null);

        return response()->json([
            'message' => 'E-mail confirmado. Entre novamente para continuar.',
            'requires_login' => true,
        ]);
    }

    public function resend(
        ResendEmailVerificationRequest $request,
        EmailVerificationService $service,
    ): JsonResponse {
        $service->reenviarConfirmacao($request->validated('email'));

        return response()->json([
            'message' => 'Se a conta existir e ainda não estiver confirmada, enviaremos um novo link.',
        ], 202);
    }
}

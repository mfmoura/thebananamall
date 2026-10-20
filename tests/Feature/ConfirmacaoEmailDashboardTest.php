<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Notifications\ConfirmarEmailNotification;
use App\Services\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ConfirmacaoEmailDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmacao_respeita_o_perfil_em_ambos_os_endpoints(): void
    {
        foreach ([true, false] as $admin) {
            $usuario = Usuario::factory()->create(['email_validado' => false, 'email_verified_at' => null]);
            if ($admin) {
                $usuario->administrador()->create(['paginas' => ['usuarios']]);
            }
            $token = str_repeat($admin ? 'a' : 'b', 64);
            $key = 'email:verificacao:'.hash('sha256', $token);
            Redis::shouldReceive('get')->with($key)->twice()->andReturn(json_encode([
                'usuario_id' => $usuario->id, 'email' => $usuario->email, 'acao' => 'confirmar',
            ]));
            Redis::shouldReceive('exists')->with($key)->twice()->andReturn(1);
            Redis::shouldReceive('del')->with($key)->once()->andReturn(1);

            $correto = $admin ? '/dashboard/email/confirmar' : '/api/email/confirmar';
            $incorreto = $admin ? '/api/email/confirmar' : '/dashboard/email/confirmar';
            $this->postJson($incorreto, ['token' => $token])->assertUnprocessable();
            $this->assertFalse($usuario->fresh()->email_validado);
            $this->postJson($correto, ['token' => $token])->assertOk();
            $this->assertTrue($usuario->fresh()->email_validado);
        }
    }

    public function test_links_apontam_para_a_pagina_do_perfil(): void
    {
        Notification::fake();
        Redis::shouldReceive('setex')->twice()->andReturn(true);
        Redis::shouldReceive('exists')->twice()->andReturn(1);
        foreach ([true, false] as $admin) {
            $usuario = Usuario::factory()->create(['email_validado' => false]);
            if ($admin) {
                $usuario->administrador()->create(['paginas' => ['usuarios']]);
            }
            app(EmailVerificationService::class)->enviarConfirmacaoInicial($usuario);
            Notification::assertSentOnDemand(ConfirmarEmailNotification::class, function ($notification, $channels, $notifiable) use ($usuario, $admin) {
                $url = $notification->toMail($notifiable)->actionUrl;
                return $notifiable->routes['mail'] === $usuario->email
                    && parse_url($url, PHP_URL_PATH) === ($admin ? '/dashboard/email/verify' : '/email/verify');
            });
        }
    }

    public function test_paginas_administrativas_sao_acessiveis_sem_login(): void
    {
        foreach (['email/verify', 'email/pendente', 'senha-obrigatoria'] as $pagina) {
            $this->get('/dashboard/'.$pagina)->assertOk()->assertViewIs('dashboard');
        }
    }
}

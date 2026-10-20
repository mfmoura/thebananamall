<?php

namespace Tests\Feature;

use App\Models\Usuario;
use App\Notifications\ConfirmarEmailNotification;
use App\Services\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ConfirmacaoAdministradorPrincipalTest extends TestCase
{
    use RefreshDatabase;

    public function test_reenvio_renova_prazo_do_principal_expirado_e_enfileira_confirmacao(): void
    {
        Notification::fake();
        Redis::shouldReceive('setex')->once()->andReturn(true);
        Redis::shouldReceive('exists')->once()->andReturn(1);
        $usuario = Usuario::factory()->create([
            'login' => Usuario::LOGIN_ADMINISTRADOR_PRINCIPAL,
            'email_validado' => false,
            'must_change_password' => true,
            'email_confirmation_expires_at' => now()->subDay(),
        ]);

        $this->postJson('/api/email/reenviar-confirmacao', ['email' => $usuario->email])->assertStatus(202);

        $this->assertTrue($usuario->fresh()->email_confirmation_expires_at->isFuture());
        $this->assertFalse($usuario->fresh()->email_validado);
        $this->assertTrue((bool) $usuario->fresh()->must_change_password);
        Notification::assertSentOnDemand(ConfirmarEmailNotification::class, function ($notification, $channels, $notifiable) use ($usuario): bool {
            return $notifiable->routes['mail'] === $usuario->email
                && $notification instanceof \Illuminate\Contracts\Queue\ShouldQueue
                && $notification->connection === 'redis' && $notification->queue === 'emails';
        });
    }

    public function test_demais_contas_expiradas_nao_recebem_reenvio_inclusive_sob_bloqueio(): void
    {
        Notification::fake();
        $usuario = Usuario::factory()->create([
            'email_validado' => false, 'email_confirmation_expires_at' => now()->subDay(),
        ]);
        $this->postJson('/api/email/reenviar-confirmacao', ['email' => $usuario->email])->assertStatus(202);
        app(EmailVerificationService::class)->enviarConfirmacaoInicial($usuario);
        Notification::assertNothingSent();
        $this->assertTrue($usuario->fresh()->email_confirmation_expires_at->isPast());
    }

    public function test_outro_administrador_tambem_pode_renovar_link_expirado(): void
    {
        Notification::fake();
        Redis::shouldReceive('setex')->once()->andReturn(true);
        Redis::shouldReceive('exists')->once()->andReturn(1);
        $usuario = Usuario::factory()->create([
            'login' => 'outro-admin', 'email_validado' => false,
            'email_confirmation_expires_at' => now()->subDay(),
        ]);
        $usuario->administrador()->create(['paginas' => ['estoque']]);
        $this->postJson('/api/email/reenviar-confirmacao', ['email' => $usuario->email])->assertStatus(202);
        Notification::assertSentOnDemand(ConfirmarEmailNotification::class);
        $this->assertTrue($usuario->fresh()->email_confirmation_expires_at->isFuture());
    }

    public function test_login_pendente_sinaliza_confirmacao_sem_disparar_email(): void
    {
        Notification::fake();
        $usuario = Usuario::factory()->create([
            'login' => Usuario::LOGIN_ADMINISTRADOR_PRINCIPAL, 'email_validado' => false,
            'password' => 'SenhaDeTeste@2026', 'must_change_password' => true,
        ]);
        $this->postJson('/oauth/login', ['login' => $usuario->login, 'password' => 'SenhaDeTeste@2026'])
            ->assertForbidden()->assertJsonPath('code', 'email_confirmation_required');
        Notification::assertNothingSent();
    }
}

<?php

namespace Tests\Feature;

use App\Notifications\ConfirmarEmailNotification;
use App\Notifications\RecuperarSenhaNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Mail\Markdown;
use Tests\TestCase;

class EmailIdiomaTest extends TestCase
{
    public function test_prazos_dos_links_usam_duracoes_legiveis_em_portugues(): void
    {
        foreach ([1 => '1 minuto', 60 => '1 hora', 90 => '1 hora e 30 minutos', 120 => '2 horas'] as $minutos => $duracao) {
            foreach ([
                new ConfirmarEmailNotification('https://example.com/confirmar', false, $minutos),
                new ConfirmarEmailNotification('https://example.com/confirmar', true, $minutos),
                new RecuperarSenhaNotification('https://example.com/recuperar', $minutos),
            ] as $notification) {
                $mail = $notification->toMail(new AnonymousNotifiable);

                $this->assertContains('Este link tem validade de até '.$duracao.' a partir da solicitação.', $mail->outroLines);
                $this->assertSame('pt_BR', $notification->locale);
            }
        }
    }

    public function test_modelos_html_e_texto_dos_emails_sao_traduzidos(): void
    {
        $this->app->setLocale('pt_BR');
        $notification = new RecuperarSenhaNotification('https://example.com/recuperar', 90);
        $mail = $notification->toMail(new AnonymousNotifiable);
        $markdown = $this->app->make(Markdown::class);

        foreach (['render', 'renderText'] as $metodo) {
            $conteudo = (string) $markdown->$metodo('notifications::email', $mail->data());

            $this->assertStringContainsString('Atenciosamente,', $conteudo);
            $this->assertStringContainsString('Todos os direitos reservados.', $conteudo);
            $this->assertStringContainsString('Se você não conseguir clicar no botão', $conteudo);
            $this->assertStringNotContainsString('Regards,', $conteudo);
            $this->assertStringNotContainsString("If you're having trouble", $conteudo);
        }
    }
}

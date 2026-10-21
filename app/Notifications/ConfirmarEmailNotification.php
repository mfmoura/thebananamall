<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ConfirmarEmailNotification extends EmailEnfileiradoNotification
{

    public function __construct(
        private readonly string $url,
        private readonly bool $trocaEmail,
        private readonly int $minutosExpiracao,
        private readonly ?int $usuarioId = null,
    ) {
        parent::__construct();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ($this->usuarioId === null || \App\Models\Usuario::whereKey($this->usuarioId)->exists())
            && $this->tokenAindaValido($this->url, 'email:verificacao:');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensagem = (new MailMessage)
            ->subject($this->trocaEmail ? 'Confirme seu novo e-mail' : 'Confirme seu e-mail')
            ->greeting("Ol\u{00E1}!");

        if ($this->trocaEmail) {
            $mensagem->line("Para concluir a troca do e-mail, confirme o novo endere\u{00E7}o e escolha uma nova senha.");
        } else {
            $mensagem->line("Confirme seu endere\u{00E7}o de e-mail para liberar o acesso \u{00E0} sua conta.");
        }

        return $mensagem
            ->action('Confirmar e-mail', $this->url)
            ->line('Este link tem validade de até '.$this->duracaoLegivel($this->minutosExpiracao).' a partir da solicitação.')
            ->line("Se voc\u{00EA} n\u{00E3}o solicitou esta a\u{00E7}\u{00E3}o, ignore esta mensagem.");
    }
}

<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class RecuperarSenhaNotification extends EmailEnfileiradoNotification
{

    public function __construct(
        private readonly string $url,
        private readonly int $minutosExpiracao,
        private readonly ?int $usuarioId = null,
    ) {
        parent::__construct();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ($this->usuarioId === null || \App\Models\Usuario::whereKey($this->usuarioId)->exists())
            && $this->tokenAindaValido($this->url, 'senha:recuperacao:token:');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Recupera\u{00E7}\u{00E3}o de senha")
            ->greeting("Ol\u{00E1}!")
            ->line("Recebemos uma solicita\u{00E7}\u{00E3}o para redefinir a senha da sua conta.")
            ->action('Redefinir senha', $this->url)
            ->line('Este link tem validade de até '.$this->duracaoLegivel($this->minutosExpiracao).' a partir da solicitação.')
            ->line("Se voc\u{00EA} n\u{00E3}o solicitou esta a\u{00E7}\u{00E3}o, ignore esta mensagem.");
    }
}

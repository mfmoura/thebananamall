<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmarEmailNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $url,
        private readonly bool $trocaEmail,
        private readonly int $minutosExpiracao,
    ) {}

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
            ->line("Este link expira em {$this->minutosExpiracao} minutos.")
            ->line("Se voc\u{00EA} n\u{00E3}o solicitou esta a\u{00E7}\u{00E3}o, ignore esta mensagem.");
    }
}

<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class AlertaSenhaAntigaNotification extends EmailEnfileiradoNotification
{
    public function __construct(private readonly ?int $usuarioId = null)
    {
        parent::__construct();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->usuarioId === null || \App\Models\Usuario::whereKey($this->usuarioId)->exists();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Alerta de tentativa de acesso')
            ->greeting('Olá!')
            ->line('Foi detectada uma tentativa de login usando uma senha antiga da sua conta.')
            ->line('Se não foi você, redefina sua senha e revise a segurança da sua conta.')
            ->line('Por segurança, não incluímos a senha nem outros dados sensíveis neste e-mail.');
    }
}

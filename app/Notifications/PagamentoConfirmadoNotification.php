<?php

namespace App\Notifications;

use App\Models\Pedido;
use App\Services\PedidoFiscalService;
use Illuminate\Notifications\Messages\MailMessage;

class PagamentoConfirmadoNotification extends EmailEnfileiradoNotification
{
    public function __construct(public readonly int $pedidoId)
    {
        parent::__construct();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $notifiable->newQuery()->whereKey($notifiable->getKey())->exists()
            && Pedido::query()->whereKey($this->pedidoId)
                ->whereIn('status', PedidoFiscalService::PAGOS)
                ->whereHas('cliente', fn ($query) => $query->whereNull('deleted_at')->where('usuario_id', $notifiable->getKey()))
                ->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Pagamento confirmado — pedido #{$this->pedidoId}")
            ->greeting('Olá!')
            ->line("Recebemos a confirmação do pagamento do seu pedido #{$this->pedidoId}.")
            ->line('Você pode acompanhar o andamento do pedido na loja.')
            ->action('Ver pedido', url('/pedidos/'.$this->pedidoId));
    }
}

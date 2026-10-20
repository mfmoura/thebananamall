<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\MercadoPagoAssinaturaService;

class MercadoPagoWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $secret = config('mercadopago.webhook_secret');
        abort_unless(is_string($secret) && $secret !== '', 503, 'Webhook indisponível.');
        $id = $this->query('data_id'); // PHP normaliza o ponto de data.id para underscore.
        $id = is_string($id) ? trim($id) : null;
        $requestId = trim((string) $this->header('x-request-id', ''));
        $signature = trim((string) $this->header('x-signature', ''));
        if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9]+$/D', $id) || ! $requestId) {
            return false;
        }
        return app(MercadoPagoAssinaturaService::class)->validar($signature, $requestId, $id, $secret);
    }

    public function rules(): array
    {
        return ['type' => ['required', 'in:order']];
    }

    public function orderId(): string
    {
        return trim($this->query('data_id'));
    }

    protected function failedAuthorization(): void
    {
        abort(401, 'Notificação não autorizada.');
    }
}

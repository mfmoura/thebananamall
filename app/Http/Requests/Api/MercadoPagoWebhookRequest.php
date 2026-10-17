<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class MercadoPagoWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $secret = config('mercadopago.webhook_secret');
        abort_unless(is_string($secret) && $secret !== '', 503, 'Webhook indisponível.');
        $id = $this->query('data_id'); // PHP normaliza o ponto de data.id para underscore.
        $requestId = $this->header('x-request-id');
        $signature = $this->header('x-signature', '');
        if (! is_string($id) || ! preg_match('/^[a-zA-Z0-9]+$/D', $id) || ! $requestId) {
            return false;
        }
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) === 2) {
                $parts[$pair[0]] = $pair[1];
            }
        }
        if (! ctype_digit($parts['ts'] ?? '') || ! isset($parts['v1'])) {
            return false;
        }
        $manifest = 'id:'.$id.';request-id:'.$requestId.';ts:'.$parts['ts'].';';

        return hash_equals(hash_hmac('sha256', $manifest, $secret), $parts['v1']);
    }

    public function rules(): array
    {
        return ['type' => ['required', 'in:order']];
    }

    public function orderId(): string
    {
        return $this->query('data_id');
    }
}

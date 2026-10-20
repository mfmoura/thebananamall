<?php

namespace App\Services;

use App\Enums\StatusNotaFiscal;
use App\Models\NotaFiscal;
use App\Models\Usuario;
use App\Webservices\NfeIoClient;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NfeIoNotaFiscalService
{
    public function __construct(
        private readonly NotaFiscalService $notas,
        private readonly NfeIoClient $client,
        private readonly NfeIoPayloadService $payload,
        private readonly DanfeService $danfe,
    ) {}

    public function emitir(Usuario $usuario, array $dados): NotaFiscal
    {
        $cnpj = $this->client->verificarAmbiente();

        return $this->comBloqueio($dados['idempotency_key'], function () use ($usuario, $dados, $cnpj): NotaFiscal {
            $hash = hash('sha256', json_encode($this->ordenar($dados), JSON_THROW_ON_ERROR));
            $nota = NotaFiscal::withTrashed()->where('emissao_idempotency_key', $dados['idempotency_key'])->first();
            if ($nota && ($nota->trashed() || $nota->emissao_hash !== $hash || $nota->emitente_documento !== $cnpj || $nota->provedor !== 'nfe_io')) {
                throw new HttpException(409, 'A idempotency_key já foi utilizada. Consulte a emissão original.');
            }
            if (! $nota) {
                $duplicada = NotaFiscal::withTrashed()->where('emitente_documento', $cnpj)->where('ambiente', 'homologacao')
                    ->where('serie', (string) (int) $dados['serie'])->where('numero', (string) (int) $dados['numero'])->exists();
                if ($duplicada || (int) $dados['serie'] !== (int) config('services.nfe_io.serie')) {
                    throw new HttpException(409, 'Número já utilizado ou série diferente da inscrição estadual configurada.');
                }
                $dominio = Arr::only($dados, ['pedido_id', 'natureza_operacao', 'total_frete']);
                $nota = $this->notas->prepararEmissao($usuario, $dominio + [
                    'provedor' => 'nfe_io', 'referencia_provedor' => null,
                    'numero' => (string) (int) $dados['numero'], 'serie' => (string) (int) $dados['serie'], 'data_emissao' => now(),
                    'itens' => array_map(static fn ($item) => Arr::except($item, ['tributos']), $dados['itens']),
                ], [
                    'ambiente' => 'homologacao', 'emitente_documento' => $cnpj,
                    'emissao_idempotency_key' => $dados['idempotency_key'], 'emissao_hash' => $hash,
                ]);
            }
            if ($nota->referencia_provedor !== null) {
                return $nota->load('itens');
            }
            if ($nota->emissao_enviada) {
                throw new HttpException(409, 'Emissão NFE.io com resultado incerto. Reconcilie a referência no painel antes de continuar; não repita o POST.');
            }
            if ($nota->emissao_payload === null) {
                $nota->forceFill(['emissao_payload' => $this->payload->montar($nota, $dados)])->save();
            }
            // Persistir ANTES da chamada: timeout, queda do worker ou falha ao salvar o ID não autorizam outro POST.
            $nota->forceFill(['emissao_enviada' => true, 'status' => StatusNotaFiscal::Processando])->save();
            $remoto = $this->client->emitir($nota->emissao_payload);
            $id = $this->client->identificador((string) ($remoto['id'] ?? ''));
            $nota->forceFill(['referencia_provedor' => $id])->save();

            return $nota->load('itens');
        });
    }

    public function sincronizar(NotaFiscal $nota): NotaFiscal
    {
        $this->client->verificarAmbiente();
        $this->validarNota($nota);

        return $this->comBloqueio($nota->emissao_idempotency_key, function () use ($nota): NotaFiscal {
            $nota->refresh();
            if (! $nota->referencia_provedor) {
                throw new HttpException(409, 'Reconcilie a emissão NFE.io no painel antes de continuar.');
            }
            $estado = $this->client->consultar($nota->referencia_provedor);
            if (($estado['id'] ?? null) !== $nota->referencia_provedor
                || (int) ($estado['number'] ?? 0) !== (int) $nota->numero
                || (int) ($estado['serie'] ?? 0) !== (int) $nota->serie
                || str_pad((string) data_get($estado, 'issuer.federalTaxNumber', ''), 14, '0', STR_PAD_LEFT) !== $nota->emitente_documento) {
                throw new HttpException(502, 'A resposta NFE.io não corresponde à nota consultada.');
            }
            $status = match ($estado['status'] ?? '') {
                'Issued' => StatusNotaFiscal::Autorizada,
                'Cancelled' => StatusNotaFiscal::Cancelada,
                'Error', 'IssueDenied' => StatusNotaFiscal::Rejeitada,
                'Disabled' => StatusNotaFiscal::Erro,
                'Created', 'Processing', 'IssuedContingency' => StatusNotaFiscal::Processando,
                default => throw new HttpException(502, 'Status NFE.io desconhecido.'),
            };
            if ($nota->status === StatusNotaFiscal::Cancelada
                || ($nota->estaAutorizada() && ! in_array($status, [StatusNotaFiscal::Autorizada, StatusNotaFiscal::Cancelada], true))) {
                return $nota->load('itens');
            }
            if ($status === StatusNotaFiscal::Autorizada) {
                if (! $nota->caminho_danfe || ! Storage::disk('local')->exists($nota->caminho_danfe)) {
                    $xml = $this->client->baixarXml($nota->referencia_provedor);
                    // A autorização vem do XML autorizado, não apenas do status JSON.
                    $this->danfe->armazenarAutorizada($nota, $xml, (string) data_get($estado, 'authorization.accessKey'));
                }
            } else {
                $nota->forceFill(['status' => $status])->save();
            }

            return $nota->load('itens');
        });
    }

    public function baixar(NotaFiscal $nota, string $tipo): StreamedResponse
    {
        $this->validarNota($nota);
        $caminho = $tipo === 'xml' ? $nota->caminho_xml : $nota->caminho_danfe;
        if (! $nota->estaAutorizada() || ! $caminho || ! Storage::disk('local')->exists($caminho)) {
            throw new HttpException(409, 'Sincronize a nota autorizada para disponibilizar os arquivos fiscais.');
        }

        return Storage::disk('local')->download($caminho, 'homologacao-nfe-'.$nota->id.'.'.($tipo === 'xml' ? 'xml' : 'pdf'), [
            'Content-Type' => $tipo === 'xml' ? 'application/xml' : 'application/pdf',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function validarNota(NotaFiscal $nota): void
    {
        if ($nota->provedor !== 'nfe_io' || $nota->ambiente !== 'homologacao' || $nota->emitente_documento !== config('services.nfe_io.cnpj')) {
            throw new HttpException(409, 'A nota não pertence à configuração NFE.io de homologação.');
        }
    }

    private function comBloqueio(string $chave, \Closure $operacao): mixed
    {
        try {
            return Cache::lock('lock:fiscal-nfeio:'.$chave, 180)->block(5, $operacao);
        } catch (LockTimeoutException|UniqueConstraintViolationException) {
            throw new HttpException(409, 'Emissão já registrada ou em processamento. Consulte a original.');
        }
    }

    private function ordenar(array $dados): array
    {
        if (! array_is_list($dados)) {
            ksort($dados);
        }
        foreach ($dados as &$valor) {
            if (is_array($valor)) {
                $valor = $this->ordenar($valor);
            }
        }

        return $dados;
    }
}

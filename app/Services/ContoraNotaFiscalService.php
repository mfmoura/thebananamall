<?php

namespace App\Services;

use App\Enums\StatusNotaFiscal;
use App\Models\NotaFiscal;
use App\Models\Usuario;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ContoraNotaFiscalService
{
    public function __construct(
        private readonly NotaFiscalService $notas,
        private readonly FiscalContoraClient $client,
        private readonly ContoraPayloadService $payload,
        private readonly DanfeService $danfe,
    ) {}

    public function emitir(Usuario $usuario, array $dados): NotaFiscal
    {
        $cnpj = $this->client->verificarAmbiente();
        $chave = $dados['idempotency_key'];

        return $this->comBloqueio($chave, function () use ($usuario, $dados, $cnpj, $chave): NotaFiscal {
            $hash = hash('sha256', json_encode($this->ordenar($dados), JSON_THROW_ON_ERROR));
            $nota = NotaFiscal::query()->where('emissao_idempotency_key', $chave)->first();
            if ($nota && ($nota->emissao_hash !== $hash || $nota->emitente_documento !== $cnpj)) {
                throw new HttpException(409, 'A idempotency_key já foi utilizada com outros dados.');
            }
            if ($nota === null) {
                $nota = $this->preparar($usuario, $dados, $cnpj, $hash);
            }
            if ($nota->emissao_payload === null) {
                $nota->forceFill(['emissao_payload' => $this->payload->montar($nota, $dados)])->save();
            }
            if ($nota->referencia_provedor === null) {
                if ($nota->created_at->lt(now()->subHours(23))) {
                    throw new HttpException(409, 'Reconcilie o rascunho no painel fiscal antes de repetir: a janela de idempotência do provedor está expirando.');
                }
                $remoto = $this->client->criarRascunho($nota->emissao_payload, $cnpj, $chave);
                if (! Str::isUuid($remoto['id'] ?? '')) {
                    throw new HttpException(502, 'O provedor não retornou o identificador da nota.');
                }
                $nota->forceFill(['referencia_provedor' => $remoto['id']])->save();
            }

            // Após timeout, consultar antes de transmitir evita reenviar uma emissão já enfileirada.
            $estado = $this->client->consultar($nota->referencia_provedor, $cnpj);
            $this->atualizarEstado($nota, $estado);
            $ocupada = in_array(data_get($estado, 'lifecycle.processing_status'), ['queued', 'processing'], true)
                || data_get($estado, 'lifecycle.status') === 'authorize_pending';
            if ($ocupada) {
                $nota->forceFill(['emissao_enviada' => true])->save();
            }
            if (! $nota->emissao_enviada && ! $ocupada
                && in_array(data_get($estado, 'lifecycle.status'), ['draft', 'built_unsigned', 'built_signed'], true)) {
                $this->client->transmitir($nota->referencia_provedor, $cnpj, $chave.'-authorize');
                $nota->forceFill(['emissao_enviada' => true, 'status' => StatusNotaFiscal::Processando])->save();
            }

            return $nota->load('itens');
        });
    }

    public function sincronizar(NotaFiscal $nota): NotaFiscal
    {
        $cnpj = $this->client->verificarAmbiente();
        $this->validarNota($nota, $cnpj);

        return $this->comBloqueio($nota->emissao_idempotency_key, function () use ($nota, $cnpj): NotaFiscal {
            $nota->refresh();
            if ($nota->referencia_provedor === null) {
                throw new HttpException(409, 'Repita a emissão com a mesma idempotency_key para recuperar o rascunho.');
            }
            $this->atualizarEstado($nota, $this->client->consultar($nota->referencia_provedor, $cnpj));
            if ($nota->estaAutorizada() && (! $nota->caminho_danfe || ! Storage::disk('local')->exists($nota->caminho_danfe))) {
                $this->danfe->armazenar($nota, $this->client->baixarXml($nota->referencia_provedor, $cnpj));
            }

            return $nota->load('itens');
        });
    }

    public function baixar(NotaFiscal $nota, string $tipo): StreamedResponse
    {
        $this->validarNota($nota, (string) config('services.fiscal_contora.cnpj'));
        if (! $nota->estaAutorizada()) {
            throw new HttpException(409, 'O documento fiscal ainda não está autorizado.');
        }
        $caminho = $tipo === 'xml' ? $nota->caminho_xml : $nota->caminho_danfe;
        if (! $caminho || ! Storage::disk('local')->exists($caminho)) {
            throw new HttpException(409, 'Sincronize a nota para disponibilizar os arquivos fiscais.');
        }

        return Storage::disk('local')->download($caminho, 'homologacao-nfe-'.$nota->id.'.'.($tipo === 'xml' ? 'xml' : 'pdf'), [
            'Content-Type' => $tipo === 'xml' ? 'application/xml' : 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function preparar(Usuario $usuario, array $dados, string $cnpj, string $hash): NotaFiscal
    {
        $duplicada = NotaFiscal::query()->where('provedor', 'contora')->where('emitente_documento', $cnpj)
            ->where('ambiente', 'homologacao')->where('serie', (string) (int) $dados['serie'])
            ->where('numero', (string) (int) $dados['numero'])->exists();
        if ($duplicada) {
            throw new HttpException(409, 'Já existe uma nota com este número e série. Reutilize a idempotency_key original.');
        }

        $dominio = Arr::only($dados, ['pedido_id', 'natureza_operacao']);
        $dominio += [
            'provedor' => 'contora', 'referencia_provedor' => null,
            'numero' => (string) (int) $dados['numero'], 'serie' => (string) (int) $dados['serie'],
            'data_emissao' => now(),
            'itens' => array_map(static fn (array $item): array => Arr::except($item, ['tributos']), $dados['itens']),
        ];

        return $this->notas->prepararEmissao($usuario, $dominio, [
            'ambiente' => 'homologacao', 'emitente_documento' => $cnpj,
            'emissao_idempotency_key' => $dados['idempotency_key'], 'emissao_hash' => $hash,
        ]);
    }

    private function atualizarEstado(NotaFiscal $nota, array $estado): void
    {
        if (($estado['id'] ?? null) !== $nota->referencia_provedor) {
            throw new HttpException(502, 'O status recebido não corresponde à nota consultada.');
        }
        $situacao = data_get($estado, 'lifecycle.status');
        $sefaz = $estado['sefaz'] ?? [];
        $status = match ($situacao) {
            'authorized' => StatusNotaFiscal::Autorizada,
            'cancelled' => StatusNotaFiscal::Cancelada,
            'error' => isset($sefaz['status_code']) ? StatusNotaFiscal::Rejeitada : StatusNotaFiscal::Erro,
            'authorize_pending' => StatusNotaFiscal::Processando,
            'draft', 'built_unsigned', 'built_signed' => StatusNotaFiscal::Pendente,
            default => throw new HttpException(502, 'Status fiscal desconhecido; consulte o painel do provedor.'),
        };
        if ($nota->status === StatusNotaFiscal::Cancelada && $status !== StatusNotaFiscal::Cancelada) {
            return;
        }
        if ($status === StatusNotaFiscal::Autorizada
            && (! preg_match('/^\d{44}$/', (string) ($sefaz['access_key'] ?? ''))
                || empty($sefaz['protocol']) || empty($sefaz['authorized_at'])
                || ! in_array((string) ($sefaz['status_code'] ?? ''), ['100', '150'], true))) {
            throw new HttpException(502, 'A autorização fiscal está incompleta na resposta do provedor.');
        }
        if (in_array($nota->status, [StatusNotaFiscal::Autorizada, StatusNotaFiscal::Cancelada], true)
            && ! in_array($status, [StatusNotaFiscal::Autorizada, StatusNotaFiscal::Cancelada], true)) {
            return;
        }
        if (in_array(data_get($estado, 'lifecycle.processing_status'), ['queued', 'processing'], true)
            && $status === StatusNotaFiscal::Pendente) {
            $status = StatusNotaFiscal::Processando;
        }
        $dados = ['status' => $status];
        if ($status === StatusNotaFiscal::Autorizada) {
            $dados += ['chave_acesso' => $sefaz['access_key'], 'protocolo_autorizacao' => $sefaz['protocol'], 'data_autorizacao' => $sefaz['authorized_at']];
        }
        if ($status === StatusNotaFiscal::Rejeitada) {
            $dados += ['codigo_rejeicao' => (string) $sefaz['status_code'], 'motivo_rejeicao' => $sefaz['status_message'] ?? null];
        }
        // Homologação nunca altera estoque, mesmo após autorização SEFAZ de teste.
        $nota->forceFill($dados)->save();
    }

    private function validarNota(NotaFiscal $nota, string $cnpj): void
    {
        if ($nota->provedor !== 'contora' || $nota->ambiente !== 'homologacao' || $nota->emitente_documento !== $cnpj) {
            throw new HttpException(409, 'A nota não pertence à configuração Fiscal Contora de homologação.');
        }
    }

    private function comBloqueio(string $chave, \Closure $operacao): mixed
    {
        try {
            return Cache::lock('lock:fiscal-contora:'.$chave, 120)->block(5, $operacao);
        } catch (LockTimeoutException) {
            throw new HttpException(409, 'Esta nota está sendo processada. Aguarde antes de repetir.');
        } catch (UniqueConstraintViolationException) {
            throw new HttpException(409, 'A nota ou a chave de idempotência já foi registrada. Consulte a emissão original.');
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

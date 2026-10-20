<?php

namespace Tests\Unit;

use App\Services\ViaCepService;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\TestCase;

class ViaCepServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config(['services.viacep.base_url' => 'https://viacep.com.br']);
    }

    public function test_consulta_normaliza_cep_e_retorna_dados_do_provedor(): void
    {
        $dados = ['cep' => '01001-000', 'uf' => 'SP', 'localidade' => 'São Paulo'];
        Http::fake(['viacep.com.br/*' => Http::response($dados)]);

        $this->assertSame($dados, app(ViaCepService::class)->consultar('01001-000'));
        Http::assertSent(fn ($request) => $request->url() === 'https://viacep.com.br/ws/01001000/json/');
    }

    public function test_cep_invalido_nao_consulta_provedor(): void
    {
        try {
            app(ViaCepService::class)->consultar('123', 'endereco');
            $this->fail('CEP inválido deve ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('endereco.cep', $exception->errors());
        }
        Http::assertNothingSent();
    }

    public function test_confirmacao_preserva_validacao_de_cidade_no_service(): void
    {
        Http::fake(['*' => Http::response(['cep' => '01001-000', 'uf' => 'SP', 'localidade' => 'São Paulo'])]);
        try {
            app(ViaCepService::class)->confirmarEndereco(['cep' => '01001000', 'estado' => 'SP', 'cidade' => 'Campinas'], 'dados_fiscais.endereco');
            $this->fail('Cidade divergente deve ser rejeitada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('dados_fiscais.endereco.cidade', $exception->errors());
        }
    }

    public static function falhas(): array
    {
        return [
            'cep invalido no provedor' => [400, [], ValidationException::class],
            'cep inexistente' => [200, ['erro' => true], ValidationException::class],
            'resposta incompleta' => [200, ['cep' => '01001000'], ServiceUnavailableHttpException::class],
            'provedor indisponivel' => [503, [], ServiceUnavailableHttpException::class],
        ];
    }

    #[DataProvider('falhas')]
    public function test_falhas_preservam_excecao_publica(int $status, array $dados, string $excecao): void
    {
        Http::fake(['*' => Http::response($dados, $status)]);
        $this->expectException($excecao);
        app(ViaCepService::class)->consultar('01001000');
    }

    public function test_timeout_do_cliente_externo_preserva_indisponibilidade(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->expectException(ServiceUnavailableHttpException::class);
        app(ViaCepService::class)->consultar('01001000');
    }
}

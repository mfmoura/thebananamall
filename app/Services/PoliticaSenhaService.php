<?php

namespace App\Services;

use App\Models\HistoricoSenha;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PoliticaSenhaService
{
    private const HISTORICO_MAXIMO = 4;

    public function corresponde(string $senha, string $hash): bool
    {
        // The second check permits existing hashes created before the pepper was introduced.
        return Hash::check($this->pepperizar($senha), $hash)
            || Hash::check($senha, $hash);
    }

    /** @param list<string|null> $contextos */
    public function hash(string $senha, array $contextos = []): string
    {
        $this->validarContexto($senha, $contextos);

        return Hash::make($this->pepperizar($senha));
    }

    /** @param list<string|null> $contextosAdicionais */
    public function validarNova(Usuario $usuario, string $senha, array $contextosAdicionais = []): void
    {
        $this->validarContexto($senha, [
            $usuario->login,
            $usuario->email,
            ...$contextosAdicionais,
        ]);

        $this->validarHistorico($usuario, $senha);
    }

    private function validarHistorico(Usuario $usuario, string $senha): void
    {
        if ($this->corresponde($senha, (string) $usuario->password)) {
            throw ValidationException::withMessages([
                'password' => 'A nova senha deve ser diferente da senha atual e das quatro anteriores.',
            ]);
        }

        foreach ($usuario->historicoSenhas()->latest('id')->limit(self::HISTORICO_MAXIMO)->get() as $registro) {
            if ($this->corresponde($senha, $registro->password_hash)) {
                throw ValidationException::withMessages([
                    'password' => 'A nova senha deve ser diferente da senha atual e das quatro anteriores.',
                ]);
            }
        }
    }

    /** @param list<string|null> $contextosAdicionais */
    public function alterar(Usuario $usuario, string $novaSenha, array $contextosAdicionais = []): void
    {
        $this->validarNova($usuario, $novaSenha, $contextosAdicionais);

        DB::transaction(function () use ($usuario, $novaSenha, $contextosAdicionais): void {
            $usuarioBloqueado = Usuario::query()->lockForUpdate()->findOrFail($usuario->getKey());

            // Revalida as regras dependentes da conta sob bloqueio de linha.
            $this->validarContexto($novaSenha, [
                $usuarioBloqueado->login,
                $usuarioBloqueado->email,
                ...$contextosAdicionais,
            ]);
            $this->validarHistorico($usuarioBloqueado, $novaSenha);
            HistoricoSenha::query()->create([
                'usuario_id' => $usuarioBloqueado->getKey(),
                'password_hash' => $usuarioBloqueado->password,
            ]);
            $usuarioBloqueado->password = Hash::make($this->pepperizar($novaSenha));
            $usuarioBloqueado->save();

            $idsAntigos = $usuarioBloqueado->historicoSenhas()
                ->latest('id')
                ->skip(self::HISTORICO_MAXIMO)
                ->pluck('id');

            if ($idsAntigos->isNotEmpty()) {
                HistoricoSenha::query()->whereIn('id', $idsAntigos)->delete();
            }

            $usuario->setAttribute('password', $usuarioBloqueado->password);
            $usuario->syncOriginalAttribute('password');
        });
    }

    private function pepperizar(string $senha): string
    {
        $chave = (string) config('services.password_pepper');

        if ($chave === '') {
            throw new \RuntimeException('Configure PASSWORD_PEPPER ou APP_KEY para proteger senhas.');
        }

        return hash_hmac('sha256', $senha, $chave);
    }

    /** @param list<string|null> $contextos */
    private function validarContexto(string $senha, array $contextos): void
    {
        $termos = [
            ...$contextos,
            config('app.name'),
            'Liga Comunista Brasileira',
            'Loja Virtual LCB',
            'LCB',
        ];
        $senhaNormalizada = $this->normalizarContexto($senha);

        foreach ($termos as $termo) {
            if (! is_string($termo) || trim($termo) === '') {
                continue;
            }

            $candidatos = [$termo];
            if (str_contains($termo, '@')) {
                $candidatos[] = explode('@', $termo, 2)[0];
            }

            foreach ($candidatos as $candidato) {
                $candidatoNormalizado = $this->normalizarContexto($candidato);
                $variantesLeetspeak = [
                    'a' => '[a4]',
                    'e' => '[e3]',
                    'i' => '[i1]',
                    'o' => '[o0]',
                    's' => '[s5]',
                    't' => '[t7]',
                ];
                $termoRegex = implode('', array_map(
                    fn (string $caractere): string => $variantesLeetspeak[$caractere]
                        ?? preg_quote($caractere, '/'),
                    str_split($candidatoNormalizado),
                ));

                if (
                    $candidatoNormalizado !== ''
                    && preg_match(
                        '/^(?:[0-9]{1,4})?'.$termoRegex.'(?:[0-9]{1,4})?$/',
                        $senhaNormalizada,
                    ) === 1
                ) {
                    throw ValidationException::withMessages([
                        'password' => 'A senha nao pode ser igual ao login, e-mail ou nome da loja, nem uma variacao previsivel.',
                    ]);
                }
            }
        }
    }

    private function normalizarContexto(string $valor): string
    {
        $ascii = Str::lower(Str::ascii($valor));
        $semSubstituicoes = strtr($ascii, [
            '@' => 'a',
            '$' => 's',
        ]);

        return preg_replace('/[^a-z0-9]/', '', $semSubstituicoes) ?? '';
    }
}

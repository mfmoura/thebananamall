<?php

namespace Tests\Unit\Enums;

use App\Enums\NivelAcesso;
use App\Enums\TipoUsuario;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsuarioEnumsTest extends TestCase
{
    #[Test]
    public function tipo_usuario_enum_expone_os_tipos_de_perfil_suportados(): void
    {
        self::assertSame('cliente', TipoUsuario::Cliente->value);
        self::assertSame('administrador', TipoUsuario::Administrador->value);
        self::assertSame('logistica', TipoUsuario::Logistica->value);
        self::assertNull(TipoUsuario::tryFrom('desconhecido'));
    }

    #[Test]
    public function nivel_de_acesso_enum_expone_niveis_conhecidos(): void
    {
        self::assertSame(['contabilidade', 'estoque', 'manutencao', 'usuarios', 'pedidos'], array_column(NivelAcesso::cases(), 'value'));
        self::assertNull(NivelAcesso::tryFrom('super_administrador'));
        self::assertNull(NivelAcesso::tryFrom('desconhecido'));
    }
}

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
        self::assertSame('administrador', NivelAcesso::Administrador->value);
        self::assertSame('super_administrador', NivelAcesso::SuperAdministrador->value);
        self::assertNull(NivelAcesso::tryFrom('desconhecido'));
    }
}

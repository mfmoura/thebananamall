<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class Usuario extends Authenticatable implements OAuthenticatable
{
    public const LOGIN_ADMINISTRADOR_PRINCIPAL = 'admin';

    use \Illuminate\Database\Eloquent\SoftDeletes;

    /** @use HasFactory<UsuarioFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** The existing Laravel migration creates the "users" table. */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'login',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'must_change_password',
        'must_reauthenticate',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'email_confirmation_expires_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'email_validado' => 'boolean',
            'must_change_password' => 'boolean',
            'must_reauthenticate' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class);
    }

    public function administrador(): HasOne
    {
        return $this->hasOne(Administrador::class);
    }

    public function logistica(): HasOne
    {
        return $this->hasOne(Logistica::class);
    }

    public function visitante(): HasOne
    {
        return $this->hasOne(Visitante::class);
    }

    public function enderecos(): HasMany
    {
        return $this->hasMany(Endereco::class);
    }

    public function historicoSenhas(): HasMany
    {
        return $this->hasMany(HistoricoSenha::class, 'usuario_id');
    }
}

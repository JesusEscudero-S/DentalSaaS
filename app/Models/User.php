<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Tabla `users` del esquema: la autenticación real la resuelve un proveedor externo
 * (auth_provider_id); no hay columna de contraseña. Ver módulo 1.
 */
class User extends Authenticatable
{
    use HasFactory, HasUuids;

    protected $fillable = ['full_name', 'email', 'auth_provider_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /** La tabla no tiene remember_token. */
    protected $rememberTokenName = '';

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function getAuthPassword(): string
    {
        return '';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Usuario del sistema. El login es con Laravel Breeze (email + contraseña);
 * las columnas password / remember_token las agrega la migración
 * 2025_01_02_000001_add_auth_columns_to_users, y las de seguridad de la cuenta
 * (teléfono, cambio obligatorio y bloqueo) la 2026_11_25_000001 (módulo 1).
 */
class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    /** P1: intentos fallidos seguidos antes de bloquear la cuenta. */
    public const MAX_FAILED_ATTEMPTS = 5;

    /** P1: minutos que dura el bloqueo. */
    public const LOCK_MINUTES = 15;

    protected $fillable = [
        'full_name', 'email', 'phone', 'password', 'auth_provider_id', 'is_active',
        'must_change_password', 'failed_login_attempts', 'locked_until',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'must_change_password' => 'boolean',
        'failed_login_attempts' => 'integer',
        'locked_until' => 'datetime',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }
}

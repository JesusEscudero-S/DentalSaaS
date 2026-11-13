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
 * 2025_01_02_000001_add_auth_columns_to_users.
 */
class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = ['full_name', 'email', 'password', 'auth_provider_id', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }
}

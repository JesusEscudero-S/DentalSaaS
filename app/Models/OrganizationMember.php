<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrganizationMember extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = ['organization_id', 'user_id', 'role_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function doctorProfile(): HasOne
    {
        return $this->hasOne(DoctorProfile::class, 'member_id');
    }

    /** Miembros activos con perfil de doctor (odontólogos). */
    public function scopeDoctors(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereHas('doctorProfile');
    }

    public function displayName(): string
    {
        return $this->user?->full_name ?? '—';
    }

    /** Su rol es el de administrador del sistema (super rol). */
    public function isAdmin(): bool
    {
        return (bool) $this->role?->is_system_admin;
    }

    /** RN-U5: es el único administrador activo de la clínica. */
    public function isLastActiveAdmin(): bool
    {
        if (! $this->is_active || ! $this->isAdmin()) {
            return false;
        }

        return ! static::query()
            ->whereKeyNot($this->id)
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->where('is_system_admin', true)->where('status', 'active'))
            ->exists();
    }

    /** Payload de los modales de Gestión de usuarios (patrón toDetailArray de Agenda). */
    public function toUserArray(): array
    {
        $current = app(CurrentOrganization::class)->member();
        $isSelf = $current?->id === $this->id;

        return [
            'id' => $this->id,
            'full_name' => $this->user->full_name,
            'email' => $this->user->email,
            'role_id' => $this->role_id,
            'professional_license' => $this->doctorProfile?->professional_license,
            'specialty' => $this->doctorProfile?->specialty,
            'label' => $this->user->full_name.' ('.$this->user->email.')',
            'can_deactivate' => $this->is_active && ! $isSelf && ! $this->isLastActiveAdmin(),
            'can_reset' => ! $isSelf,
            'urls' => [
                'update' => route('users.update', $this),
                'deactivate' => route('users.deactivate', $this),
                'reactivate' => route('users.reactivate', $this),
                'reset' => route('users.reset-password', $this),
            ],
        ];
    }
}

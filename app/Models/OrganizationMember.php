<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
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
}

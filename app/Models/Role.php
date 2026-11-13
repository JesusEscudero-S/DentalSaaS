<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = ['organization_id', 'code', 'name', 'description', 'is_system_admin', 'status'];

    protected $casts = ['is_system_admin' => 'boolean'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot('organization_id', 'granted_at');
    }
}

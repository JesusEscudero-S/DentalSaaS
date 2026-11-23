<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\MovementDirection;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Categoría de ingreso/egreso. Se desactiva, no se borra. */
class FinancialCategory extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'name', 'direction', 'status'];

    protected $attributes = ['status' => 'active'];

    protected $casts = [
        'direction' => MovementDirection::class,
        'status' => ActiveStatus::class,
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(FinancialMovement::class, 'category_id');
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ActiveStatus::Active->value);
    }

    public function scopeDirection(Builder $query, MovementDirection $direction): Builder
    {
        return $query->where('direction', $direction->value);
    }
}

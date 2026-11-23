<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\CashSessionStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Caja de una sucursal. Se desactiva, no se borra. */
class CashRegister extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'branch_id', 'name', 'status'];

    protected $attributes = ['status' => 'active'];

    protected $casts = ['status' => ActiveStatus::class];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /** La sesión abierta (a lo más una, por el índice único uq_cash_one_open). */
    public function openSession(): HasOne
    {
        return $this->hasOne(CashSession::class)->where('status', CashSessionStatus::Open->value);
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ActiveStatus::Active->value);
    }
}

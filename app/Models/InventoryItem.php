<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Insumo / material del catálogo de inventario (RF 10.1). Se desactiva, no se borra (RN-I9).
 * Sus existencias viven en inventory_stock (una fila por sucursal; la clínica usa una sola).
 */
class InventoryItem extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'name', 'sku', 'category', 'unit_of_measure', 'supplier_id', 'status'];

    protected $attributes = ['status' => 'active'];

    protected $casts = ['status' => ActiveStatus::class];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Existencias en la sucursal de la clínica (hay una sola sucursal). */
    public function stock(): HasOne
    {
        return $this->hasOne(InventoryStock::class, 'item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'item_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(InventoryLot::class, 'item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ActiveStatus::Active->value);
    }

    /** Búsqueda por nombre o SKU (?q=). */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        if ($term === '') {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like));
    }

    /** RN-I8: insumos activos cuya cantidad actual quedó en o por debajo del nivel mínimo. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->active()->whereHas('stock', fn ($q) => $q->whereColumn('quantity_on_hand', '<=', 'min_quantity'));
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }

    public function quantity(): string
    {
        return (string) ($this->stock?->quantity_on_hand ?? '0');
    }

    public function minQuantity(): string
    {
        return (string) ($this->stock?->min_quantity ?? '0');
    }

    public function isOutOfStock(): bool
    {
        return bccomp($this->quantity(), '0', 4) <= 0;
    }

    /** RN-I8: la alerta se activa con cantidad ≤ mínimo. Un insumo dado de baja ya no alerta (RF 10.3). */
    public function isLowStock(): bool
    {
        return $this->isActive() && bccomp($this->quantity(), $this->minQuantity(), 4) <= 0;
    }

    /** Semáforo de la fila: danger (sin stock), warning (en o bajo el mínimo) o null. */
    public function stockLevel(): ?string
    {
        if (! $this->isLowStock()) {
            return null;
        }

        return $this->isOutOfStock() ? 'danger' : 'warning';
    }

    /** "8.0000" → "8"; "2.5000" → "2.5" (las existencias se guardan con 4 decimales). */
    public static function formatQuantity(string|int|float|null $quantity): string
    {
        $formatted = number_format((float) $quantity, 4, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /** "8 cartucho", como en Figma. */
    public function quantityLabel(): string
    {
        return self::formatQuantity($this->quantity()).' '.$this->unit_of_measure;
    }

    /** Datos para los modales de la fila (editar, entrada, salida, desactivar). */
    public function toDetailArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'category' => $this->category ?? '',
            'unit_of_measure' => $this->unit_of_measure,
            'supplier_name' => $this->supplier?->name ?? '',
            'min_quantity' => self::formatQuantity($this->minQuantity()),
            'location' => $this->stock?->location ?? '',
            'stock' => $this->quantityLabel(),
            'updateUrl' => route('inventory.update', $this),
            'deactivateUrl' => route('inventory.deactivate', $this),
        ];
    }
}

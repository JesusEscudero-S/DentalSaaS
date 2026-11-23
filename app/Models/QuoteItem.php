<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteItem extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'quote_id', 'line_number', 'treatment_id', 'description_snapshot',
        'sat_product_key_snapshot', 'tooth_fdi', 'quantity', 'unit_price', 'discount_pct', 'tax_rate_snapshot',
        'tax_is_exempt', 'line_subtotal', 'line_discount', 'line_tax', 'line_total',
    ];

    protected $casts = [
        'line_number' => 'integer',
        'tooth_fdi' => 'integer',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_pct' => 'decimal:2',
        'tax_rate_snapshot' => 'decimal:6',
        'tax_is_exempt' => 'boolean',
        'line_subtotal' => 'decimal:2',
        'line_discount' => 'decimal:2',
        'line_tax' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Treatment::class);
    }
}

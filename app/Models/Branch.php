<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'address_line',
        'phone',
        'manager_user_id',
        'status',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];
}

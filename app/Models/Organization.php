<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasUuids;

    protected $fillable = [
        'commercial_name', 'legal_name', 'rfc', 'phone', 'email', 'address_line',
        'address_postal_code', 'currency', 'timezone', 'status',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Catálogo global de permisos (module, action), ej. pacientes.crear. */
class Permission extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['module', 'action', 'description'];
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FolioSequence extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $fillable = ['organization_id', 'document_type', 'series', 'prefix', 'last_value'];
}

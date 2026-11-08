<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** An academy program length. `months` is null when the catalogue name does not state one. */
class Duration extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['name', 'months', 'description', 'sort_order', 'is_active'];

    protected $casts = [
        'months' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];
}

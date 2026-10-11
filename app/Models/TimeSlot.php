<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An academy class time. The table is `times`; the model name avoids colliding
 * with PHP's date/time vocabulary.
 */
class TimeSlot extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'times';

    protected $fillable = ['name', 'description', 'sort_order', 'is_active'];

    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean'];
}

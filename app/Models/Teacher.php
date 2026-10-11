<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** An academy teacher who can be assigned to an academy applicant. */
class Teacher extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['name', 'contact', 'email', 'description', 'sort_order', 'is_active'];

    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean'];
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EducationalScholarship extends Model
{
    use Auditable, SoftDeletes;

    protected $table = 'educational_scholarships';

    protected $fillable = ['name', 'description', 'sort_order', 'is_active'];

    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean'];
}

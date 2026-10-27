<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** The visitor's English proficiency level. */
class EnglishLevel extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['name', 'description', 'sort_order', 'is_active'];

    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean'];

    public function visitors(): HasMany
    {
        return $this->hasMany(Visitor::class);
    }
}

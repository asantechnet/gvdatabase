<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusRuleSet extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['rules' => 'array', 'is_active' => 'boolean']; }
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicantStage extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['key', 'name', 'country_id', 'university_id', 'sort_order', 'is_active', 'is_terminal', 'allowed_next_keys'];
    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean', 'is_terminal' => 'boolean', 'allowed_next_keys' => 'array'];

    public function allowsTransitionTo(self $stage): bool
    {
        return in_array($stage->key, $this->allowed_next_keys ?? [], true);
    }
    public function country() { return $this->belongsTo(Country::class); }
    public function university() { return $this->belongsTo(University::class); }
}

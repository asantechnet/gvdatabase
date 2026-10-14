<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicantStage extends Model
{
    use Auditable, SoftDeletes;

    /**
     * The terminal stage that finishes a case successfully. It is the point at
     * which the case stops counting as work in progress: the visa is done, the
     * money is settled and the employees who sold the services earn their
     * commission (see `CommissionService`).
     */
    public const COMPLETED_KEY = 'completed';

    /**
     * The terminal stages a case is closed *at* rather than completed at. A case
     * that ends here never earns commission for its service lines.
     */
    public const EXIT_KEYS = ['cancelled', 'rejected', 'withdrawn'];

    protected $fillable = ['key', 'name', 'service', 'country_id', 'university_id', 'sort_order', 'is_active', 'is_terminal', 'allowed_next_keys'];

    protected $casts = ['sort_order' => 'integer', 'is_active' => 'boolean', 'is_terminal' => 'boolean', 'allowed_next_keys' => 'array'];

    /** Stages are scoped to one built-in service (see Migration 2026_10_13_000001). */
    public function scopeForService($query, ?string $service)
    {
        return $service ? $query->where('service', $service) : $query;
    }

    public function allowsTransitionTo(self $stage): bool
    {
        return in_array($stage->key, $this->allowed_next_keys ?? [], true);
    }

    /** True for the stage that completes a case. */
    public function isCompletedStage(): bool
    {
        return $this->key === self::COMPLETED_KEY;
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function university()
    {
        return $this->belongsTo(University::class);
    }
}

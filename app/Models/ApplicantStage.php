<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Exceptions\HttpException;
use Illuminate\Validation\ValidationException;

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

    // `is_terminal` and `is_locked` are deliberately absent from `$fillable`:
    // the terminal outcome of a case is system-owned, so only migrations and
    // future seeds may write it, never a request body.
    protected $fillable = ['key', 'name', 'service', 'country_id', 'university_id', 'test_id', 'sort_order', 'is_active', 'allowed_next_keys'];

    // The casts keep the JSON contract boolean for the stage editor, and the
    // defaults keep a just-created row's response identical to a row read from
    // the database.
    protected $attributes = ['is_locked' => false, 'is_terminal' => false];

    protected $casts = ['test_id' => 'integer', 'sort_order' => 'integer', 'is_active' => 'boolean', 'is_terminal' => 'boolean', 'is_locked' => 'boolean', 'allowed_next_keys' => 'array'];

    protected static function booted(): void
    {
        // A new stage is never terminal: the only terminal rows are the
        // canonical outcomes written by migrations.
        static::creating(function (self $stage): void {
            if ($stage->is_terminal) {
                throw ValidationException::withMessages(['is_terminal' => ['business.applicant_stage_terminal_managed']]);
            }
            $stage->is_terminal = false;
        });
        static::updating(function (self $stage): void {
            // The terminal flag is system-owned. A direct property write is
            // refused even when a future controller forgets the API check.
            if ($stage->isDirty('is_terminal')) {
                throw ValidationException::withMessages(['is_terminal' => ['business.applicant_stage_terminal_managed']]);
            }
            // Defence in depth for the controller's `409`: even a future code
            // path that loads a locked stage and calls `update()`/`delete()`
            // cannot change or remove it.
            $stage->assertUnlocked();
        });
        static::deleting(fn (self $stage) => $stage->assertUnlocked());
    }

    /** A platform-seeded terminal stage, frozen for every role. */
    public function isLocked(): bool
    {
        return (bool) $this->is_locked;
    }

    /** @throws HttpException when the stage is frozen */
    public function assertUnlocked(): void
    {
        if ($this->isLocked()) {
            throw new HttpException(409, 'business.applicant_stage_locked');
        }
    }

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

    /** The educational test a test-offer stage is limited to; null is shared. */
    public function test()
    {
        return $this->belongsTo(Test::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['applicant_id', 'name', 'description', 'status', 'start_date', 'due_date', 'created_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'due_date' => 'date'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applicant(): BelongsTo { return $this->belongsTo(Applicant::class); }

    /**
     * The catalogue services this delivery project covers.
     *
     * A project belongs to one customer, while each service remains a shared
     * catalogue entry. The controller keeps this relation scoped to services
     * that are actually assigned to that customer.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->withTimestamps()
            ->orderBy('services.type');
    }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class)->orderBy('order');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

}

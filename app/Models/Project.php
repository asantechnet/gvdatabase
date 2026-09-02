<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = ['customer_id', 'name', 'description', 'status', 'start_date', 'due_date', 'created_by'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'due_date' => 'date'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }

    public function boards(): HasMany
    {
        return $this->hasMany(Board::class)->orderBy('order');
    }

}

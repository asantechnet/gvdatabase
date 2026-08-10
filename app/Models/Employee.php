<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'father_name',
        'contact',
        'addr',
        'education_lvl',
        'position_id',
        'salary',
        'bonus',
        'ID_file',
        'assure_file',
        'department',
        'join_date',
        'contract_duration',
        'avatar',
        'user_id',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function worthyEmployees(): HasMany
    {
        return $this->hasMany(WorthyEmployee::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'salary' => 'decimal:2',
            'bonus' => 'boolean',
            'join_date' => 'date',
        ];
    }
}

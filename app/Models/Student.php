<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A durable Academy person, independent of applicants and enrollments. */
class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_number', 'applicant_id', 'name', 'father_name', 'father_phone',
        'contact', 'email', 'address', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(AcademyEnrollment::class);
    }
}

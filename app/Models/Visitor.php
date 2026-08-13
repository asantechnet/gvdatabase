<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'company',
        'contact',
        'service',
        'marketer',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];
}

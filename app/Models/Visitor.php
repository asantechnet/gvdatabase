<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;

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

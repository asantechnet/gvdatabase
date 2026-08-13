<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Marketer extends Model
{
    use Auditable, HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'status', 'notes'];
}

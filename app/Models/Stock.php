<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'serial_number',
        'item',
        'quantity',
        'model',
        'office',
        'staff_id',
    ];

    public function staff()
    {
        return $this->belongsTo(Employee::class, 'staff_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryDeduction extends Model
{
    use HasFactory;

    protected $fillable = ['description', 'amount', 'sort_order', 'is_automatic', 'code'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'sort_order' => 'integer',
            'is_automatic' => 'boolean',
        ];
    }

    public function salary(): BelongsTo
    {
        return $this->belongsTo(Salary::class);
    }
}

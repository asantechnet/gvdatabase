<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceType extends Model
{
    use HasFactory;

    protected $fillable = ['kind', 'value', 'name', 'is_system'];

    protected $attributes = ['is_system' => false];

    protected $casts = ['is_system' => 'boolean'];

    public const DEFAULTS = [
        'income' => ['Service', 'Product', 'Investment', 'Other'],
        'expense' => ['Utilities', 'Software/SaaS', 'Marketing', 'Office Supplies', 'Travel & Dining', 'Salaries', 'Pending transaction'],
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'navigation_clr',
        'cards_clr',
        'footer_clr',
        'website_title',
        'website_logo',
        'website_address',
        'phone',
        'physical_address',
        'short_company_name',
        'original_company_name',
    ];
}

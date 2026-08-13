<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory, Auditable;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $fillable = [
        'navigation_clr',
        'cards_clr',
        'footer_clr',
        'paid_off_days_per_year',
        'website_title',
        'website_logo',
        'company_signature',
        'contract_letterhead',
        'contract_cover_design',
        'website_address',
        'company_email',
        'timezone',
        'phone',
        'physical_address',
        'short_company_name',
        'original_company_name',
        'business_licence',
        'office_open_time',
        'office_close_time',
        'default_break_minutes',
    ];
}

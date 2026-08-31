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
        'mail_password' => 'encrypted',
    ];

    protected $fillable = [
        'navigation_clr',
        'cards_clr',
        'footer_clr',
        'primary_color',
        'secondary_color',
        'success_color',
        'info_color',
        'warning_color',
        'error_color',
        'light_background_color',
        'light_surface_color',
        'dark_background_color',
        'dark_surface_color',
        'paid_off_days_per_year',
        'website_title',
        'website_logo',
        'company_signature',
        'contract_letterhead',
        'contract_cover_design',
        'contract_letterhead_safe_x',
        'contract_letterhead_safe_y',
        'contract_letterhead_safe_width',
        'contract_letterhead_safe_height',
        'website_address',
        'company_email',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_from_name',
        'mail_reply_to',
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

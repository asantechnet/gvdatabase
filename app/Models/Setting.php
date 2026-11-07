<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $attributes = ['email_delivery_enabled' => false];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'mail_password' => 'encrypted',
        'automatic_attendance_enabled' => 'boolean',
        'missing_attendance_status' => 'string',
        'email_delivery_enabled' => 'boolean',
        'receipt_letterhead_safe_x' => 'float',
        'receipt_letterhead_safe_y' => 'float',
        'receipt_letterhead_safe_width' => 'float',
        'receipt_letterhead_safe_height' => 'float',
        'payslip_letterhead_safe_x' => 'float',
        'payslip_letterhead_safe_y' => 'float',
        'payslip_letterhead_safe_width' => 'float',
        'payslip_letterhead_safe_height' => 'float',
    ];

    protected $fillable = [
        'navigation_clr',
        'cards_clr',
        'footer_clr',
        'primary_color',
        'secondary_color',
        'dark_primary_color',
        'dark_secondary_color',
        'light_background_color',
        'light_surface_color',
        'dark_background_color',
        'dark_surface_color',
        'off_day_allowance_days_per_year',
        'website_title',
        'website_logo',
        'website_dark_logo',
        'favicon',
        'company_signature',
        'contract_letterhead',
        'contract_cover_design',
        'contract_letterhead_safe_x',
        'contract_letterhead_safe_y',
        'contract_letterhead_safe_width',
        'contract_letterhead_safe_height',
        'receipt_letterhead',
        'receipt_letterhead_source',
        'receipt_letterhead_safe_x',
        'receipt_letterhead_safe_y',
        'receipt_letterhead_safe_width',
        'receipt_letterhead_safe_height',
        'payslip_letterhead',
        'payslip_letterhead_source',
        'payslip_letterhead_safe_x',
        'payslip_letterhead_safe_y',
        'payslip_letterhead_safe_width',
        'payslip_letterhead_safe_height',
        'website_address',
        'company_email',
        'mail_host',
        'mail_port',
        'mail_encryption',
        'mail_username',
        'mail_password',
        'mail_from_name',
        'mail_reply_to',
        'email_delivery_enabled',
        'timezone',
        'phone',
        'physical_address',
        'short_company_name',
        'original_company_name',
        'business_licence',
        'office_open_time',
        'office_close_time',
        'default_break_minutes',
        'automatic_attendance_enabled',
        'missing_attendance_status',
    ];
}

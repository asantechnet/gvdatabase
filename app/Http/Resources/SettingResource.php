<?php

namespace App\Http\Resources;

use App\Support\DocumentLetterheads;
use App\Support\ThemePalette;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SettingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'navigation_clr' => $this->navigation_clr,
            'cards_clr' => $this->cards_clr,
            'footer_clr' => $this->footer_clr,
            ...collect(ThemePalette::fields())
                ->mapWithKeys(fn (string $field) => [$field => ThemePalette::value($field, $this->{$field})])
                ->all(),
            'paid_leave_days_per_year' => (float) $this->paid_leave_days_per_year,
            'website_title' => $this->website_title,
            'website_logo' => $this->website_logo,
            'website_logo_url' => $this->website_logo ? Storage::url($this->website_logo) : null,
            'company_signature' => $this->company_signature,
            'company_signature_url' => $this->company_signature ? Storage::url($this->company_signature) : null,
            'contract_letterhead' => $this->contract_letterhead,
            'contract_letterhead_url' => $this->contract_letterhead ? Storage::url($this->contract_letterhead) : null,
            'contract_cover_design' => $this->contract_cover_design,
            'contract_cover_design_url' => $this->contract_cover_design ? Storage::url($this->contract_cover_design) : null,
            'contract_letterhead_safe_x' => (float) ($this->contract_letterhead_safe_x ?? 8.5),
            'contract_letterhead_safe_y' => (float) ($this->contract_letterhead_safe_y ?? 15),
            'contract_letterhead_safe_width' => (float) ($this->contract_letterhead_safe_width ?? 83),
            'contract_letterhead_safe_height' => (float) ($this->contract_letterhead_safe_height ?? 70),
            'receipt_letterhead' => $this->receipt_letterhead,
            'receipt_letterhead_url' => $this->receipt_letterhead ? Storage::url($this->receipt_letterhead) : null,
            'receipt_letterhead_source' => DocumentLetterheads::normalizeSource($this->receipt_letterhead_source),
            'receipt_letterhead_safe_x' => (float) ($this->receipt_letterhead_safe_x ?? 6),
            'receipt_letterhead_safe_y' => (float) ($this->receipt_letterhead_safe_y ?? 18),
            'receipt_letterhead_safe_width' => (float) ($this->receipt_letterhead_safe_width ?? 88),
            'receipt_letterhead_safe_height' => (float) ($this->receipt_letterhead_safe_height ?? 64),
            'payslip_letterhead' => $this->payslip_letterhead,
            'payslip_letterhead_url' => $this->payslip_letterhead ? Storage::url($this->payslip_letterhead) : null,
            'payslip_letterhead_source' => DocumentLetterheads::normalizeSource($this->payslip_letterhead_source),
            'payslip_letterhead_safe_x' => (float) ($this->payslip_letterhead_safe_x ?? 8),
            'payslip_letterhead_safe_y' => (float) ($this->payslip_letterhead_safe_y ?? 14),
            'payslip_letterhead_safe_width' => (float) ($this->payslip_letterhead_safe_width ?? 84),
            'payslip_letterhead_safe_height' => (float) ($this->payslip_letterhead_safe_height ?? 72),
            // Resolved artwork for printable documents (receipt / payslip):
            // the selected source plus the safe-zone where text lands.
            'letterheads' => [
                'receipt' => DocumentLetterheads::resolve($this->resource, 'receipt'),
                'payslip' => DocumentLetterheads::resolve($this->resource, 'payslip'),
            ],
            'website_address' => $this->website_address,
            'company_email' => $this->company_email,
            'mail_host' => $this->mail_host,
            'mail_port' => $this->mail_port,
            'mail_encryption' => $this->mail_encryption,
            'mail_username' => $this->mail_username,
            'mail_password_configured' => filled($this->mail_password),
            'mail_from_name' => $this->mail_from_name,
            'mail_reply_to' => $this->mail_reply_to,
            'email_delivery_enabled' => (bool) ($this->email_delivery_enabled ?? false),
            'timezone' => $this->timezone ?: config('app.timezone', 'Asia/Kabul'),
            'phone' => $this->phone,
            'physical_address' => $this->physical_address,
            'short_company_name' => $this->short_company_name,
            'original_company_name' => $this->original_company_name,
            'business_licence' => $this->business_licence,
            'office_open_time' => $this->office_open_time ? substr($this->office_open_time, 0, 5) : null,
            'office_close_time' => $this->office_close_time ? substr($this->office_close_time, 0, 5) : null,
            'default_break_minutes' => (int) $this->default_break_minutes,
            'automatic_attendance_enabled' => (bool) $this->automatic_attendance_enabled,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

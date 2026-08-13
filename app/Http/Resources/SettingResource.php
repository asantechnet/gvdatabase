<?php

namespace App\Http\Resources;

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
            'paid_off_days_per_year' => $this->paid_off_days_per_year,
            'website_title' => $this->website_title,
            'website_logo' => $this->website_logo,
            'website_logo_url' => $this->website_logo ? Storage::url($this->website_logo) : null,
            'company_signature' => $this->company_signature,
            'company_signature_url' => $this->company_signature ? Storage::url($this->company_signature) : null,
            'contract_letterhead' => $this->contract_letterhead,
            'contract_letterhead_url' => $this->contract_letterhead ? Storage::url($this->contract_letterhead) : null,
            'contract_cover_design' => $this->contract_cover_design,
            'contract_cover_design_url' => $this->contract_cover_design ? Storage::url($this->contract_cover_design) : null,
            'website_address' => $this->website_address,
            'company_email' => $this->company_email,
            'timezone' => $this->timezone ?: 'UTC',
            'phone' => $this->phone,
            'physical_address' => $this->physical_address,
            'short_company_name' => $this->short_company_name,
            'original_company_name' => $this->original_company_name,
            'business_licence' => $this->business_licence,
            'office_open_time' => $this->office_open_time ? substr($this->office_open_time, 0, 5) : null,
            'office_close_time' => $this->office_close_time ? substr($this->office_close_time, 0, 5) : null,
            'default_break_minutes' => (int) $this->default_break_minutes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

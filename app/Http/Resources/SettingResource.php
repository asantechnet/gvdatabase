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
            'website_title' => $this->website_title,
            'website_logo' => $this->website_logo,
            'website_logo_url' => $this->website_logo ? Storage::url($this->website_logo) : null,
            'website_address' => $this->website_address,
            'phone' => $this->phone,
            'physical_address' => $this->physical_address,
            'short_company_name' => $this->short_company_name,
            'original_company_name' => $this->original_company_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

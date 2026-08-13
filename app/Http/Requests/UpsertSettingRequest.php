<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'navigation_clr' => [$required, 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'cards_clr' => [$required, 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_clr' => [$required, 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'paid_off_days_per_year' => [$required, 'integer', 'min:0', 'max:366'],
            'website_title' => [$required, 'string', 'max:255'],
            'website_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048'],
            'company_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'contract_letterhead' => [
                'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192',
                'dimensions:min_width=1000,min_height=300',
                Rule::prohibitedIf(fn () => $this->boolean('remove_contract_letterhead')),
            ],
            'contract_cover_design' => [
                'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192',
                'dimensions:min_width=1000,min_height=1400',
                Rule::prohibitedIf(fn () => $this->boolean('remove_contract_cover_design')),
            ],
            'remove_contract_letterhead' => ['sometimes', 'boolean'],
            'remove_contract_cover_design' => ['sometimes', 'boolean'],
            'website_address' => [$required, 'string', 'max:255'],
            'company_email' => [$required, 'email:rfc', 'max:255'],
            'timezone' => [$required, 'timezone:all'],
            'phone' => [$required, 'string', 'max:50'],
            'physical_address' => [$required, 'string', 'max:1000'],
            'short_company_name' => [$required, 'string', 'max:255'],
            'original_company_name' => [$required, 'string', 'max:255'],
            'business_licence' => ['nullable', 'string', 'max:255'],
            'office_open_time' => ['nullable', 'date_format:H:i'],
            'office_close_time' => ['nullable', 'date_format:H:i'],
            'default_break_minutes' => ['sometimes', 'integer', 'min:0', 'max:720'],
        ];
    }
}

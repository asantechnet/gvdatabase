<?php

namespace App\Http\Requests;

use App\Support\ThemePalette;
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
            'navigation_clr' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'cards_clr' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_clr' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ...collect(ThemePalette::fields())
                ->mapWithKeys(fn (string $field) => [$field => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/']])
                ->all(),
            'paid_leave_days_per_year' => [$required, 'numeric', 'min:0', 'max:366'],
            'website_title' => [$required, 'string', 'max:255'],
            'website_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048'],
            'company_signature' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'contract_letterhead' => [
                'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192',
                Rule::prohibitedIf(fn () => $this->boolean('remove_contract_letterhead')),
            ],
            'contract_cover_design' => [
                'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192',
                Rule::prohibitedIf(fn () => $this->boolean('remove_contract_cover_design')),
            ],
            'contract_letterhead_safe_x' => ['sometimes', 'numeric', 'min:0', 'max:95'],
            'contract_letterhead_safe_y' => ['sometimes', 'numeric', 'min:0', 'max:95'],
            'contract_letterhead_safe_width' => ['sometimes', 'numeric', 'min:5', 'max:100'],
            'contract_letterhead_safe_height' => ['sometimes', 'numeric', 'min:5', 'max:100'],
            'remove_contract_letterhead' => ['sometimes', 'boolean'],
            'remove_contract_cover_design' => ['sometimes', 'boolean'],
            'website_address' => [$required, 'string', 'max:255'],
            'company_email' => [$required, 'email:rfc', 'max:255'],
            'mail_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            'mail_encryption' => ['sometimes', 'nullable', Rule::in(['tls', 'ssl'])],
            'mail_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'clear_mail_password' => ['sometimes', 'boolean'],
            'mail_from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_reply_to' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'timezone' => [$required, 'timezone:all'],
            'phone' => [$required, 'string', 'max:50'],
            'physical_address' => [$required, 'string', 'max:1000'],
            'short_company_name' => [$required, 'string', 'max:255'],
            'original_company_name' => [$required, 'string', 'max:255'],
            'business_licence' => ['nullable', 'string', 'max:255'],
            'office_open_time' => ['nullable', 'date_format:H:i'],
            'office_close_time' => ['nullable', 'date_format:H:i'],
            'default_break_minutes' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'automatic_attendance_enabled' => ['sometimes', 'boolean'],
        ];
    }
}

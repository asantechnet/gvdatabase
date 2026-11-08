<?php

namespace App\Http\Requests;

use App\Support\DocumentLetterheads;
use App\Support\ThemePalette;
use App\Support\SettingsValidation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'navigation_clr' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'cards_clr' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_clr' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ...collect(ThemePalette::fields())
                ->mapWithKeys(fn (string $field) => [$field => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/']])
                ->all(),
            'off_day_allowance_days_per_year' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:366'],
            'website_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'website_logo' => [
                'nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048',
                Rule::prohibitedIf(fn () => $this->boolean('remove_website_logo')),
            ],
            'website_dark_logo' => [
                'nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048',
                Rule::prohibitedIf(fn () => $this->boolean('remove_website_dark_logo')),
            ],
            'favicon' => [
                'nullable', 'mimes:ico,jpeg,png,jpg,webp,gif', 'max:2048',
                Rule::prohibitedIf(fn () => $this->boolean('remove_favicon')),
            ],
            'company_signature' => [
                'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048',
                Rule::prohibitedIf(fn () => $this->boolean('remove_company_signature')),
            ],
            'remove_website_logo' => ['sometimes', 'boolean'],
            'remove_website_dark_logo' => ['sometimes', 'boolean'],
            'remove_favicon' => ['sometimes', 'boolean'],
            'remove_company_signature' => ['sometimes', 'boolean'],
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
            'receipt_letterhead' => [
                'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192',
                Rule::prohibitedIf(fn () => $this->boolean('remove_receipt_letterhead')),
            ],
            'receipt_letterhead_source' => ['sometimes', 'nullable', Rule::in(DocumentLetterheads::SOURCES)],
            'receipt_letterhead_safe_x' => ['sometimes', 'numeric', 'min:0', 'max:95'],
            'receipt_letterhead_safe_y' => ['sometimes', 'numeric', 'min:0', 'max:95'],
            'receipt_letterhead_safe_width' => ['sometimes', 'numeric', 'min:5', 'max:100'],
            'receipt_letterhead_safe_height' => ['sometimes', 'numeric', 'min:5', 'max:100'],
            'remove_receipt_letterhead' => ['sometimes', 'boolean'],
            'payslip_letterhead' => [
                'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192',
                Rule::prohibitedIf(fn () => $this->boolean('remove_payslip_letterhead')),
            ],
            'payslip_letterhead_source' => ['sometimes', 'nullable', Rule::in(DocumentLetterheads::SOURCES)],
            'payslip_letterhead_safe_x' => ['sometimes', 'numeric', 'min:0', 'max:95'],
            'payslip_letterhead_safe_y' => ['sometimes', 'numeric', 'min:0', 'max:95'],
            'payslip_letterhead_safe_width' => ['sometimes', 'numeric', 'min:5', 'max:100'],
            'payslip_letterhead_safe_height' => ['sometimes', 'numeric', 'min:5', 'max:100'],
            'remove_payslip_letterhead' => ['sometimes', 'boolean'],
            'website_address' => ['nullable', 'string', 'url:http,https', 'max:255'],
            // Company email delivery is optional: the profile saves without any
            // mail configuration and falls back to the environment mailer.
            'company_email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'email_delivery_enabled' => ['sometimes', 'boolean'],
            'mail_host' => ['sometimes', 'nullable', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (is_string($value) && ! filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && ! filter_var($value, FILTER_VALIDATE_IP)) {
                    $fail(__('settings.validation.smtpHost', ['attribute' => __('settings.fields.mail_host')]));
                }
            }],
            'mail_port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            'mail_encryption' => ['sometimes', 'nullable', Rule::in(['tls', 'ssl'])],
            'mail_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'clear_mail_password' => ['sometimes', 'boolean'],
            'mail_from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail_reply_to' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'physical_address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'short_company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'original_company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_licence' => ['nullable', 'string', 'max:255'],
            'office_open_time' => ['nullable', 'date_format:H:i'],
            'office_close_time' => ['nullable', 'date_format:H:i'],
            'default_break_minutes' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'automatic_attendance_enabled' => ['sometimes', 'boolean'],
            'missing_attendance_status' => ['sometimes', 'required', Rule::in(['present', 'absent', 'off_day'])],
            // Optional presentation aid: historical audit rows remain readable
            // in the Audits page whether or not the register colouring is on.
            'audit_coloring_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => __('settings.validation.required'),
            'regex' => __('settings.validation.color'),
            'uploaded' => __('settings.validation.uploadFailed'),
            'website_address.url' => __('settings.validation.websiteURL'),
        ];
    }

    public function attributes(): array
    {
        return (array) __('settings.fields');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $saved = $this->route('setting');
            $defaults = $saved instanceof \App\Models\Setting ? $saved->getAttributes() : [
                'contract_letterhead_safe_x' => 8.5, 'contract_letterhead_safe_y' => 15,
                'contract_letterhead_safe_width' => 83, 'contract_letterhead_safe_height' => 70,
                'receipt_letterhead_safe_x' => 6, 'receipt_letterhead_safe_y' => 18,
                'receipt_letterhead_safe_width' => 88, 'receipt_letterhead_safe_height' => 64,
                'payslip_letterhead_safe_x' => 8, 'payslip_letterhead_safe_y' => 14,
                'payslip_letterhead_safe_width' => 84, 'payslip_letterhead_safe_height' => 72,
            ];
            foreach (['contract', 'receipt', 'payslip'] as $kind) {
                SettingsValidation::safeArea($validator, $this->all(), $defaults, $kind.'_letterhead_');
            }
        });
    }
}

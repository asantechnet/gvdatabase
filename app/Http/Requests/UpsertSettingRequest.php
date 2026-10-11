<?php

namespace App\Http\Requests;

use App\Support\DocumentLetterheads;
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
            'website_logo' => [
                'nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048',
                Rule::prohibitedIf(fn () => $this->boolean('remove_website_logo')),
            ],
            'company_signature' => [
                'nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048',
                Rule::prohibitedIf(fn () => $this->boolean('remove_company_signature')),
            ],
            'remove_website_logo' => ['sometimes', 'boolean'],
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
            'website_address' => ['nullable', 'string', 'max:255'],
            // Company email delivery is optional: the profile saves without any
            // mail configuration and falls back to the environment mailer.
            'company_email' => ['sometimes', 'nullable', 'email:rfc', 'max:255'],
            'email_delivery_enabled' => ['sometimes', 'boolean'],
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

    public function messages(): array
    {
        return [
            'contract_letterhead.image' => 'validation.image',
            'contract_letterhead.mimes' => 'validation.mimes',
            'contract_letterhead.max' => 'validation.max',
            'contract_letterhead.uploaded' => 'validation.uploaded',
            'contract_cover_design.image' => 'validation.image',
            'contract_cover_design.mimes' => 'validation.mimes',
            'contract_cover_design.max' => 'validation.max',
            'contract_cover_design.uploaded' => 'validation.uploaded',
            'receipt_letterhead.image' => 'validation.image',
            'receipt_letterhead.mimes' => 'validation.mimes',
            'receipt_letterhead.max' => 'validation.max',
            'receipt_letterhead.uploaded' => 'validation.uploaded',
            'payslip_letterhead.image' => 'validation.image',
            'payslip_letterhead.mimes' => 'validation.mimes',
            'payslip_letterhead.max' => 'validation.max',
            'payslip_letterhead.uploaded' => 'validation.uploaded',
        ];
    }
}

<?php

namespace App\Services\Whatsapp;

use App\Support\WhatsappAudience;

/**
 * Centralized WhatsApp placeholder definition.
 *
 * Every `{{token}}` that may appear in a WhatsApp template is declared here
 * exactly once, together with the audience it belongs to, a human label, the
 * translation key for that label, and the field on the target record the app
 * fills it from.
 *
 * The frontend fetches this catalogue instead of duplicating the token list, so
 * the editor palette, the preview and the quick-contact prefill all agree with
 * what the server accepts. `unknownTokens()` is what stops a template from
 * shipping a placeholder that can never be filled — for example a visitor
 * template asking for an applicant-only field.
 */
final class WhatsappPlaceholderRegistry
{
    /** @var array<string, list<array{token: string, label: string, label_key: string, source: string, sample: string}>> */
    private const FIELDS = [
        WhatsappAudience::VISITOR => [
            ['token' => 'name', 'label' => 'Full Name', 'label_key' => 'pages.whatsapp.placeholders.visitor.name', 'source' => 'name', 'sample' => 'Ahmad Karimi'],
            ['token' => 'contact', 'label' => 'Phone', 'label_key' => 'pages.whatsapp.placeholders.visitor.contact', 'source' => 'contact', 'sample' => '0700 123 456'],
            ['token' => 'email', 'label' => 'Email', 'label_key' => 'pages.whatsapp.placeholders.visitor.email', 'source' => 'email', 'sample' => 'visitor@example.com'],
            ['token' => 'service', 'label' => 'Service', 'label_key' => 'pages.whatsapp.placeholders.visitor.service', 'source' => 'service', 'sample' => 'Scholarship'],
            // The token stays `counselor` so templates saved before the visitor
            // consultant rename keep working; it resolves the visitor's consultant.
            ['token' => 'counselor', 'label' => 'Consultant', 'label_key' => 'pages.whatsapp.placeholders.visitor.counselor', 'source' => 'consultant.name', 'sample' => 'Sara Noori'],
            ['token' => 'date', 'label' => 'Visit Date', 'label_key' => 'pages.whatsapp.placeholders.visitor.date', 'source' => 'date', 'sample' => '2026-10-14'],
        ],
        WhatsappAudience::APPLICANT => [
            ['token' => 'name', 'label' => 'Full Name', 'label_key' => 'pages.whatsapp.placeholders.applicant.name', 'source' => 'name', 'sample' => 'Ahmad Karimi'],
            ['token' => 'father_name', 'label' => 'Father Name', 'label_key' => 'pages.whatsapp.placeholders.applicant.father_name', 'source' => 'father_name', 'sample' => 'Karim Karimi'],
            ['token' => 'father_phone', 'label' => 'Father Phone', 'label_key' => 'pages.whatsapp.placeholders.applicant.father_phone', 'source' => 'father_phone', 'sample' => '0700 654 321'],
            ['token' => 'backup_phone', 'label' => 'Backup Phone', 'label_key' => 'pages.whatsapp.placeholders.applicant.backup_phone', 'source' => 'backup_phone', 'sample' => '0700 888 999'],
            ['token' => 'contact', 'label' => 'Phone', 'label_key' => 'pages.whatsapp.placeholders.applicant.contact', 'source' => 'contact', 'sample' => '0700 123 456'],
            ['token' => 'email', 'label' => 'Email', 'label_key' => 'pages.whatsapp.placeholders.applicant.email', 'source' => 'email', 'sample' => 'applicant@example.com'],
            ['token' => 'address', 'label' => 'Address', 'label_key' => 'pages.whatsapp.placeholders.applicant.address', 'source' => 'address', 'sample' => 'Kabul, Afghanistan'],
            ['token' => 'tax_number', 'label' => 'Tax Number', 'label_key' => 'pages.whatsapp.placeholders.applicant.tax_number', 'source' => 'tax_number', 'sample' => '100200300'],
            ['token' => 'service', 'label' => 'Service', 'label_key' => 'pages.whatsapp.placeholders.applicant.service', 'source' => 'service', 'sample' => 'Scholarship'],
            ['token' => 'stage', 'label' => 'Current Stage', 'label_key' => 'pages.whatsapp.placeholders.applicant.stage', 'source' => 'current_stage.name', 'sample' => 'Consultation'],
            ['token' => 'responsible_employee', 'label' => 'Responsible Employee', 'label_key' => 'pages.whatsapp.placeholders.applicant.responsible_employee', 'source' => 'responsible_employee.name', 'sample' => 'Sara Noori'],
            ['token' => 'admission_employee', 'label' => 'Admission Employee', 'label_key' => 'pages.whatsapp.placeholders.applicant.admission_employee', 'source' => 'admission_employee.name', 'sample' => 'Bilal Ahmad'],
        ],
        WhatsappAudience::EMPLOYEE => [
            ['token' => 'name', 'label' => 'Full Name', 'label_key' => 'pages.whatsapp.placeholders.employee.name', 'source' => 'name', 'sample' => 'Ahmad Karimi'],
            ['token' => 'father_name', 'label' => 'Father Name', 'label_key' => 'pages.whatsapp.placeholders.employee.father_name', 'source' => 'father_name', 'sample' => 'Karim Karimi'],
            ['token' => 'contact', 'label' => 'Phone', 'label_key' => 'pages.whatsapp.placeholders.employee.contact', 'source' => 'contact', 'sample' => '0700 123 456'],
            ['token' => 'email', 'label' => 'Email', 'label_key' => 'pages.whatsapp.placeholders.employee.email', 'source' => 'email', 'sample' => 'employee@example.com'],
            ['token' => 'position', 'label' => 'Position', 'label_key' => 'pages.whatsapp.placeholders.employee.position', 'source' => 'position', 'sample' => 'Counselor'],
            ['token' => 'department', 'label' => 'Department', 'label_key' => 'pages.whatsapp.placeholders.employee.department', 'source' => 'department_name', 'sample' => 'Admissions'],
            ['token' => 'office', 'label' => 'Office', 'label_key' => 'pages.whatsapp.placeholders.employee.office', 'source' => 'office.name', 'sample' => 'Main Office'],
            ['token' => 'join_date', 'label' => 'Join Date', 'label_key' => 'pages.whatsapp.placeholders.employee.join_date', 'source' => 'join_date', 'sample' => '2025-03-01'],
        ],
    ];

    /**
     * The placeholder catalogue for the requested audiences.
     *
     * @param  list<string>  $audiences
     * @return list<array{audience: string, label: string, label_key: string, fields: list<array{token: string, label: string, label_key: string, source: string, sample: string}>}>
     */
    public static function groupsFor(array $audiences): array
    {
        return collect(WhatsappAudience::ALL)
            ->filter(fn (string $audience) => in_array($audience, $audiences, true))
            ->map(fn (string $audience) => [
                'audience' => $audience,
                'label' => ucfirst($audience),
                'label_key' => WhatsappAudience::labelKey($audience),
                'fields' => self::fieldsFor($audience),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{token: string, label: string, label_key: string, source: string, sample: string}>
     */
    public static function fieldsFor(string $audience): array
    {
        return self::FIELDS[$audience] ?? [];
    }

    /** @return list<string> */
    public static function tokensFor(string $audience): array
    {
        return array_column(self::fieldsFor($audience), 'token');
    }

    /**
     * Placeholders used by a message that the audience cannot fill.
     *
     * @return list<string>
     */
    public static function unknownTokens(string $audience, string $message): array
    {
        $known = self::tokensFor($audience);

        return collect(self::usedTokens($message))
            ->reject(fn (string $token) => in_array($token, $known, true))
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function usedTokens(string $message): array
    {
        preg_match_all('/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/', $message, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }
}

<?php

namespace App\Support;

class RoleCatalog
{
    public const NAMES = [
        'super_admin',
        'admin',
        'manager',
        'team_lead',
        'senior_counselor',
        'finance_officer',
        'counselor',
        'case_officer',
        'staff',
        'document_officer',
        'viewer',
        'applicant',
    ];

    public static function isKnown(string $role): bool
    {
        return in_array($role, self::NAMES, true);
    }
}

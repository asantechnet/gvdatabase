<?php

namespace App\Support;

use App\Models\User;

/**
 * The record kinds a WhatsApp template can be written for.
 *
 * An audience is the record type whose data fills the template's placeholders,
 * and it also decides who may read and manage the templates: access follows the
 * record type the message is about, so nobody gets a new permission to send a
 * message they could not already make about that record.
 *
 * The value is stored as a string column (`whatsapp_templates.audience`) so
 * filters and seeded data stay readable, and more audiences can be appended
 * without a schema change.
 */
final class WhatsappAudience
{
    public const VISITOR = 'visitor';

    public const APPLICANT = 'applicant';

    public const EMPLOYEE = 'employee';

    /** @var list<string> */
    public const ALL = [self::VISITOR, self::APPLICANT, self::EMPLOYEE];

    /** Translation keys the frontend uses, keyed by audience. */
    public const LABEL_KEYS = [
        self::VISITOR => 'pages.whatsapp.audiences.visitor',
        self::APPLICANT => 'pages.whatsapp.audiences.applicant',
        self::EMPLOYEE => 'pages.whatsapp.audiences.employee',
    ];

    /** Permission that lets a user see an audience's templates. */
    private const VIEW_PERMISSIONS = [
        self::VISITOR => 'view_visitors',
        self::APPLICANT => 'view_applicants',
        self::EMPLOYEE => 'view_employees',
    ];

    /** Permission that lets a user create, edit or delete an audience's templates. */
    private const MANAGE_PERMISSIONS = [
        self::VISITOR => 'edit_visitors',
        self::APPLICANT => 'edit_applicants',
        self::EMPLOYEE => 'edit_employees',
    ];

    public static function isValid(mixed $audience): bool
    {
        return is_string($audience) && in_array($audience, self::ALL, true);
    }

    public static function labelKey(?string $audience): string
    {
        return self::LABEL_KEYS[$audience] ?? 'pages.whatsapp.audiences.unknown';
    }

    public static function viewPermission(?string $audience): ?string
    {
        return self::VIEW_PERMISSIONS[$audience] ?? null;
    }

    public static function managePermission(?string $audience): ?string
    {
        return self::MANAGE_PERMISSIONS[$audience] ?? null;
    }

    /**
     * The audiences a user may see.
     *
     * `hasPermission()` already bypasses for super admins, so the filter alone
     * gives them every audience without a second special case here.
     *
     * @return list<string>
     */
    public static function viewableBy(User $user): array
    {
        return array_values(array_filter(
            self::ALL,
            fn (string $audience) => $user->hasPermission((string) self::viewPermission($audience)),
        ));
    }

    public static function canView(User $user, ?string $audience): bool
    {
        if (! self::isValid($audience)) {
            return false;
        }

        return in_array($audience, self::viewableBy($user), true);
    }

    public static function canManage(User $user, ?string $audience): bool
    {
        $permission = self::managePermission($audience);

        return $permission !== null && $user->hasPermission($permission);
    }
}

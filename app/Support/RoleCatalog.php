<?php

namespace App\Support;

class RoleCatalog
{
    public const NAMES = ['super_admin', 'admin', 'manager', 'staff', 'viewer'];

    public static function isKnown(string $role): bool
    {
        return in_array($role, self::NAMES, true);
    }
}

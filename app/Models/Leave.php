<?php

namespace App\Models;

/**
 * Canonical HR leave record. PaidTimeOff remains a temporary compatibility
 * alias for integrations deployed before the terminology migration.
 */
class Leave extends PaidTimeOff
{
}

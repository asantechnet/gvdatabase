<?php

use App\Support\AuditRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * A model is only audited when its `audit_resource_settings` row exists and is
 * enabled, and that row was previously created lazily, the first time somebody
 * opened the audit settings screen. A freshly registered resource therefore
 * stayed silently unaudited until an administrator happened to visit that page.
 *
 * Seeding the settings here means a resource registered in `AuditRegistry` is
 * audited from its first write. `ensureSettings()` is insert-or-ignore, so this
 * is safe to run against a database that already has the rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_resource_settings')) {
            return;
        }

        AuditRegistry::ensureSettings();
    }

    public function down(): void
    {
        // Audit scope settings are configuration, not schema: the previous set
        // is left untouched so a rollback cannot silently disable auditing.
    }
};

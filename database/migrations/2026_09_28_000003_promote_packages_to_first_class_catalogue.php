<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promotes packages to a first-class catalogue product next to services.
 *
 * `package_types` always stored the packages themselves (the "type" suffix was
 * legacy naming), so it is renamed to `packages`. The scholarship/test-offer
 * `package_type_id` columns follow the same rename, and a package gains the two
 * pieces of "acts like a service" behaviour it was missing:
 *
 *  - `code`, so a package can be referenced the way a service code is, and
 *  - `contract_templates.package_id`, so a package can own the contract
 *    template that generates the agreement when it is sold to an applicant.
 *
 * Existing foreign keys (package_service, customer_packages, scholarships,
 * test_offers) follow the table rename automatically.
 */
return new class extends Migration
{
    private const LEGACY_PACKAGE_LINKS = ['scholarships', 'test_offers'];

    public function up(): void
    {
        if (Schema::hasTable('package_types') && ! Schema::hasTable('packages')) {
            Schema::rename('package_types', 'packages');
        }

        Schema::table('packages', function (Blueprint $table) {
            $table->string('code', 80)->nullable()->unique()->after('name');
        });

        foreach (self::LEGACY_PACKAGE_LINKS as $tableName) {
            if (Schema::hasColumn($tableName, 'package_type_id') && ! Schema::hasColumn($tableName, 'package_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->renameColumn('package_type_id', 'package_id');
                });
            }
        }

        if (! Schema::hasColumn('contract_templates', 'package_id')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->foreignId('package_id')->nullable()->after('service_id')
                    ->constrained('packages')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_id');
        });

        foreach (self::LEGACY_PACKAGE_LINKS as $tableName) {
            if (Schema::hasColumn($tableName, 'package_id') && ! Schema::hasColumn($tableName, 'package_type_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->renameColumn('package_id', 'package_type_id');
                });
            }
        }

        Schema::table('packages', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        Schema::rename('packages', 'package_types');
    }
};

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the complete local/demo dataset in dependency order.
     *
     * This intentionally calls every concrete seeder in this directory. Some
     * seeders create sample operational data, so production deployments should
     * run targeted seeders rather than `db:seed` without a class.
     */
    public function run(): void
    {
        $this->call([
            // Access-control reference data must exist before user accounts.
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,

            // Shared reference records used by people and operations.
            CurrencySeeder::class,
            OfficeSeeder::class,
            PositionSeeder::class,

            // Identity records depend on positions and roles.
            SuperAdminSeeder::class,
            EmployeeSeeder::class,

            // Catalogues before records that reference them.
            ServiceSeeder::class,
            ApplicantSeeder::class,
            ContractSeeder::class,
            AssetSeeder::class,

            // Work-management records and their relationship seeders.
            BoardSeeder::class,
            TagSeeder::class,
            TaskSeeder::class,
            TaskUserSeeder::class,
            TaskTagSeeder::class,
            TaskDependencySeeder::class,
            CommentSeeder::class,
            SubtaskSeeder::class,
            KanbanSeeder::class,

            // Operational and dependent sample records.
            AttendanceSeeder::class,
            InvoiceSeeder::class,
            SalarySeeder::class,
            WorthyEmployeeSeeder::class,
            VisitorSeeder::class,
            NotificationSeeder::class,

            // New CRM relationship records and educational visa operations.
            CrmSeeder::class,
            EducationalSeeder::class,
        ]);
    }
}

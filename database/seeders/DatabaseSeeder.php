<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the complete local/demo dataset in dependency order.
     *
     * The chain models the real workflow: reference catalogues first, then the
     * workforce, then applicants and their issued contracts, then the Kanban
     * work that delivers those contracts, and finally the operational records
     * (attendance, payroll, visitors, notifications, CRM). Some seeders create
     * sample operational data, so production deployments should run targeted
     * seeders rather than `db:seed` without a class.
     */
    public function run(): void
    {
        $this->call([
            // Access-control reference data must exist before user accounts.
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,

            // Shared reference records used by people, contracts and services.
            CurrencySeeder::class,
            FinanceTypeSeeder::class,
            OfficeSeeder::class,
            PositionSeeder::class,
            EducationalReferenceSeeder::class,
            // Academy is hidden from Templates & Policies, so its template is
            // ensured here rather than created through the admin UI.
            AcademyContractTemplateSeeder::class,
            // Ensure template versions for all built-in services (private, scholarship,
            // test_offer, academy) so contract generation never aborts on missing versions.
            ContractTemplateVersionSeeder::class,

            // Identity records depend on positions, roles and offices.
            SuperAdminSeeder::class,
            EmployeeSeeder::class,

            // Applicant cases then the contracts that sell them services.
            ApplicantSeeder::class,
            ContractSeeder::class,

            // Work-management records: boards, tasks and their relationships.
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
            AssetSeeder::class,
            AttendanceSeeder::class,
            SalarySeeder::class,
            WorthyEmployeeSeeder::class,
            VisitorSeeder::class,
            NotificationSeeder::class,
            CrmSeeder::class,

            // Applicant-portal demo identity last, so it can reuse everything.
            ApplicantPortalDemoSeeder::class,
        ]);
    }
}

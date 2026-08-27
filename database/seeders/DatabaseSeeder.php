<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Disable foreign key checks temporarily
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Clear all tables
        DB::table('invoice_service')->truncate();
        DB::table('salary_worthy_employee')->truncate();
        DB::table('salaries')->truncate();
        DB::table('incomes')->truncate();
        DB::table('invoices')->truncate();
        DB::table('customer_service')->truncate();
        DB::table('customers')->truncate();
        DB::table('services')->truncate();
        DB::table('currencies')->truncate();
        DB::table('stocks')->truncate();
        DB::table('visitors')->truncate();
        DB::table('task_user')->truncate();
        DB::table('board_user')->truncate();
        DB::table('board_user')->truncate();
        DB::table('task_tag')->truncate();
        DB::table('task_dependencies')->truncate();
        DB::table('comments')->truncate();
        DB::table('subtasks')->truncate();
        DB::table('tasks')->truncate();
        DB::table('tags')->truncate();
        DB::table('boards')->truncate();
        DB::table('notification_user')->truncate();
        DB::table('notifications')->truncate();
        DB::table('user_roles')->truncate();
        DB::table('role_permissions')->truncate();

        // Reset auto-increments
        DB::statement('ALTER TABLE currencies AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE services AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE customers AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE stocks AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE visitors AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE boards AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE tags AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE tasks AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE subtasks AUTO_INCREMENT = 1');
        DB::statement('ALTER TABLE comments AUTO_INCREMENT = 1');

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Seed in the correct order - roles and permissions must exist before users
        $this->call([
            CurrencySeeder::class,
            ServiceSeeder::class,
            CustomerSeeder::class,
            ContractSeeder::class,
            PositionSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
            SuperAdminSeeder::class, // This creates users (needs roles + permissions to exist)
            OfficeSeeder::class,
            StockSeeder::class,
            EmployeeSeeder::class,
            VisitorSeeder::class,
            AttendanceSeeder::class,
            SalarySeeder::class,
            WorthyEmployeeSeeder::class,
            BoardSeeder::class,
            TagSeeder::class,
            TaskSeeder::class,
            TaskUserSeeder::class,
            SubtaskSeeder::class,
            CommentSeeder::class,
            TaskTagSeeder::class,
            TaskDependencySeeder::class,
            KanbanSeeder::class,
            InvoiceSeeder::class,
            NotificationSeeder::class,
            BoardSeeder::class,
            TagSeeder::class,
            TaskSeeder::class,
            TaskUserSeeder::class,
            SubtaskSeeder::class,
            CommentSeeder::class,
            TaskTagSeeder::class,
            TaskDependencySeeder::class,
            KanbanSeeder::class,
            InvoiceSeeder::class,
            NotificationSeeder::class,
        ]);
    }
}

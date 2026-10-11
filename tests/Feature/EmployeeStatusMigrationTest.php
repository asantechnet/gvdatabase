<?php

namespace Tests\Feature;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeStatusMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_migration_compiles_for_mysql_without_retired_columns(): void
    {
        $connection = new MySqlConnection(null, 'migration_compilation');
        $connection->useDefaultSchemaGrammar();
        $statements = [];

        Schema::shouldReceive('table')->once()->with('employees', \Mockery::on(
            function ($callback) use ($connection, &$statements): bool {
                $statements = (new Blueprint($connection, 'employees', $callback))->toSql();

                return true;
            },
        ));

        $migration = require database_path('migrations/2026_10_07_000001_add_status_to_employees_table.php');
        $migration->up();

        $this->assertCount(2, $statements);
        $this->assertStringContainsString("add `status` varchar(20) not null default 'active'", $statements[0]);
        $this->assertStringNotContainsString(' after ', $statements[0]);
        $this->assertStringNotContainsString('employment_type', implode(' ', $statements));
        $this->assertStringContainsString('employees_status_index', $statements[1]);
    }
}

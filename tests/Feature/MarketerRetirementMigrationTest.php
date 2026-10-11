<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarketerRetirementMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function retirement(): object
    {
        return require database_path('migrations/2026_10_09_000001_retire_marketers_and_add_agency_staff_assignments.php');
    }

    public function test_partial_retirement_handles_unconstrained_columns_and_custom_foreign_keys(): void
    {
        // The assignment columns and earlier removals already committed, but
        // the pivot has a marketer column without the assumed foreign key.
        Schema::create('marketers', fn (Blueprint $table) => $table->id());
        Schema::table('visitors', function (Blueprint $table) {
            $table->unsignedBigInteger('marketer_id')->nullable();
            $table->foreign('marketer_id', 'legacy_visitor_marketer_fk')->references('id')->on('marketers');
        });
        Schema::table('applicant_contract', fn (Blueprint $table) => $table->unsignedBigInteger('marketer_id')->nullable()->index());
        Schema::table('contracts', fn (Blueprint $table) => $table->string('marketer')->nullable());

        $this->retirement()->up();

        $this->assertFalse(Schema::hasTable('marketers'));
        $this->assertFalse(Schema::hasColumn('visitors', 'marketer_id'));
        $this->assertFalse(Schema::hasColumn('applicant_contract', 'marketer_id'));
        $this->assertFalse(Schema::hasColumn('contracts', 'marketer'));
        $this->assertTrue(Schema::hasColumn('visitors', 'counselor_employee_id'));
        $this->assertTrue(Schema::hasColumn('applicants', 'admission_employee_id'));
        $this->assertFalse(collect(Schema::getIndexes('applicant_contract'))->contains(fn ($index) => in_array('marketer_id', $index['columns'], true)));
    }

    public function test_rerunning_retirement_repairs_assignment_constraints_without_duplicate_records(): void
    {
        Schema::table('visitors', fn (Blueprint $table) => $table->dropForeign(['counselor_employee_id']));
        $this->retirement()->up();
        $positions = DB::table('positions')->count();
        $permissions = DB::table('permissions')->count();
        $grants = DB::table('role_permissions')->count();

        $this->retirement()->up();

        $this->assertTrue(collect(Schema::getForeignKeys('visitors'))->contains(fn ($foreign) => $foreign['columns'] === ['counselor_employee_id']));
        $this->assertTrue(collect(Schema::getForeignKeys('applicants'))->contains(fn ($foreign) => $foreign['columns'] === ['admission_employee_id']));
        $this->assertSame($positions, DB::table('positions')->count());
        $this->assertSame($permissions, DB::table('permissions')->count());
        $this->assertSame($grants, DB::table('role_permissions')->count());
    }
}

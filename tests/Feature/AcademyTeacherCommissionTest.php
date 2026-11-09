<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\CommissionAccrual;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\EmployeeServiceCommission;
use App\Models\Position;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;

class AcademyTeacherCommissionTest extends AcademyTestCase
{
    use RefreshDatabase;

    public function test_completing_a_class_accrues_commission_for_its_employee_teacher(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);

        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $program = Program::create(['name' => 'English Language', 'is_active' => true]);
        $position = Position::firstOrCreate(
            ['system_key' => 'teacher'],
            ['title' => 'Teacher', 'status' => 'active', 'salary' => 50000],
        );
        $teacher = Employee::factory()->create([
            'name' => 'Jane Teacher',
            'position_id' => $position->id,
            'is_commission_based' => true,
        ]);
        EmployeeServiceCommission::create([
            'employee_id' => $teacher->id,
            'service' => 'academy',
            'percentage' => 10,
        ]);

        $classId = $this->postJson('/api/academy/classes', [
            'name' => 'Evening English',
            'program_id' => $program->id,
            'teacher_ids' => [$teacher->id],
            'weekdays' => [1, 3],
            'starts_on' => '2027-01-01',
            'ends_on' => '2027-03-31',
            'fee' => 1000,
            'is_active' => true,
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/academy/classes/{$classId}/complete")
            ->assertOk()
            ->assertJsonPath('data.id', $classId);

        $this->assertNotNull(AcademyClass::find($classId)->completed_at);
        $this->assertDatabaseHas('commission_accruals', [
            'academy_class_id' => $classId,
            'employee_id' => $teacher->id,
            'service' => 'academy',
        ]);
        $this->assertSame(100.0, (float) CommissionAccrual::query()
            ->where('academy_class_id', $classId)
            ->where('employee_id', $teacher->id)
            ->value('commission_base_amount'));

        // Completion is idempotent: a second click never duplicates the money.
        $this->patchJson("/api/academy/classes/{$classId}/complete")->assertOk();
        $this->assertSame(1, DB::table('commission_accruals')->where('academy_class_id', $classId)->count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\CommissionAccrual;
use App\Models\Currency;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use App\Services\CommissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;

class AcademyCommissionPathTest extends AcademyTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
        Currency::query()->update(['is_base' => false]);
        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
    }

    public function test_direct_academy_enrollment_creates_no_counselor_commission(): void
    {
        $class = AcademyClass::factory()->create(['fee' => 500]);

        $this->postJson('/api/academy/students', [
            'name' => 'Retired Path Student',
            'class_id' => $class->id,
        ])->assertCreated();

        $this->assertSame(0, CommissionAccrual::query()->where('service', 'academy')->count());
    }

    public function test_a_non_teacher_class_teacher_does_not_accrue_academy_commission(): void
    {
        $position = Position::query()->where('system_key', 'counselor')->first()
            ?? Position::factory()->create([
                'title' => 'Counselor',
                'system_key' => 'counselor',
            ]);
        $employee = Employee::factory()->create([
            'position_id' => $position->id,
            'is_commission_based' => true,
        ]);
        $employee->serviceCommissions()->create([
            'service' => 'academy',
            'percentage' => 10,
        ]);

        $class = AcademyClass::factory()->create([
            'fee' => 1000,
            'completed_at' => now(),
        ]);
        $class->teachers()->sync([$employee->id]);

        app(CommissionService::class)->syncForAcademyClass($class->fresh());

        $this->assertDatabaseMissing('commission_accruals', [
            'academy_class_id' => $class->id,
            'employee_id' => $employee->id,
        ]);
    }
}

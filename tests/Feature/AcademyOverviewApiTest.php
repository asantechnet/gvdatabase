<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\AcademyEnrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;

class AcademyOverviewApiTest extends AcademyTestCase
{
    use RefreshDatabase;

    public function test_overview_sums_active_seats_terms_and_completed_this_month(): void
    {
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);

        $class = AcademyClass::factory()->create([
            'capacity' => 10,
            'weekdays' => [now()->isoWeekday()],
            'starts_at' => '08:00:00',
        ]);
        $other = AcademyClass::factory()->create(['capacity' => null]);

        $one = Student::factory()->create();
        $two = Student::factory()->create();
        AcademyEnrollment::factory()->create([
            'class_id' => $class->id,
            'student_id' => $one->id,
            'status' => 'active',
            'ends_on' => now()->addDays(20)->toDateString(),
        ]);
        AcademyEnrollment::factory()->create([
            'class_id' => $class->id,
            'student_id' => $two->id,
            'status' => 'active',
            'ends_on' => now()->addDays(60)->toDateString(),
        ]);
        AcademyEnrollment::factory()->create([
            'class_id' => $other->id,
            'student_id' => Student::factory(),
            'status' => 'withdrawn',
        ]);
        AcademyEnrollment::factory()->create([
            'class_id' => $other->id,
            'student_id' => Student::factory(),
            'status' => 'completed',
            'completed_on' => now()->toDateString(),
        ]);

        $this->getJson('/api/academy/overview')->assertOk()
            ->assertJsonPath('data.active_students', 2)
            ->assertJsonPath('data.active_enrollments', 2)
            ->assertJsonPath('data.classes', 2)
            ->assertJsonPath('data.seats_filled', 2)
            ->assertJsonPath('data.capacity', 10)
            ->assertJsonPath('data.ending_soon', 1)
            ->assertJsonPath('data.completed_this_month', 1)
            ->assertJsonPath('data.today_classes.0.id', $class->id);
    }
}

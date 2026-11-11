<?php

namespace Tests\Feature;

use App\Models\AcademyClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\AcademyTestCase;

class AcademyStudentApiTest extends AcademyTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    public function test_direct_student_uses_the_class_end_date_and_a_compact_student_number(): void
    {
        $class = AcademyClass::factory()->create([
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addMonths(2)->toDateString(),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/academy/students', [
            'name' => 'Direct Academy Student',
            'class_id' => $class->id,
            'started_on' => now()->addDay()->toDateString(),
            // A stale client may still submit this field. It must not control
            // an enrollment term; the class is the sole source of the end.
            'ends_on' => now()->addYear()->toDateString(),
        ])->assertCreated()
            ->assertJsonPath('data.started_on', now()->addDay()->toDateString())
            ->assertJsonPath('data.ends_on', $class->ends_on->toDateString());

        $this->assertMatchesRegularExpression(
            '/^STU-\d{2}-\d{3}(?:-\d+)?$/',
            (string) $response->json('data.student.student_number'),
        );
    }
}

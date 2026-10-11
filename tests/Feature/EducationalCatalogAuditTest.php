<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The catalogues added for the built-in services must be auditable like every
 * other business table: registering a model without an audit resource left its
 * changes invisible in the audit trail.
 */
class EducationalCatalogAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);
    }

    public function test_creating_a_catalogue_row_writes_an_audit_entry(): void
    {
        $subject = Subject::create(['name' => 'Mathematics']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'subject_type' => 'subjects',
            'auditable_id' => $subject->id,
        ]);
    }

    public function test_updating_a_catalogue_row_records_the_changed_column(): void
    {
        $teacher = \App\Models\Teacher::create(['name' => 'Teacher One']);

        $teacher->update(['contact' => '+93700000000']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'updated',
            'subject_type' => 'teachers',
            'auditable_id' => $teacher->id,
        ]);

        $audit = \App\Models\AuditLog::query()
            ->where('subject_type', 'teachers')
            ->where('event', 'updated')
            ->firstOrFail();

        $this->assertContains('contact', $audit->changed_columns);
    }
}

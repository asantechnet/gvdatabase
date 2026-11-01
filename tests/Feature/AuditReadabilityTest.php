<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ApplicantStage;
use App\Models\ApplicantStageHistory;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\User;
use App\Services\EducationalWorkflowNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditReadabilityTest extends TestCase
{
    use RefreshDatabase;

    private function stage(string $key, string $name): ApplicantStage
    {
        return ApplicantStage::create([
            'key' => $key,
            'name' => $name,
            'service' => 'scholarship',
            'sort_order' => 10,
            'is_active' => true,
            'is_terminal' => false,
        ]);
    }

    private function audit(Applicant $applicant, ?User $actor, array $overrides = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'event' => 'updated',
            'table_name' => 'applicants',
            'auditable_type' => Applicant::class,
            'auditable_id' => $applicant->id,
            'subject_type' => 'applicants',
            'subject_label' => $applicant->name,
            'user_id' => $actor?->id,
            'old_values' => [],
            'new_values' => [],
            'changed_columns' => [],
            'ip_address' => '203.0.113.7',
            'method' => 'PATCH',
            'url' => 'http://localhost/api/applicants/'.$applicant->id,
        ], $overrides));
    }

    public function test_stage_changes_expose_field_and_stage_labels(): void
    {
        $admin = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($admin, ['access']);
        $from = $this->stage('readability_from', 'University Selection');
        $to = $this->stage('readability_to', 'University Application');
        $applicant = Applicant::factory()->create(['name' => 'Ahmad Wali']);
        $audit = $this->audit($applicant, $admin, [
            'old_values' => ['current_stage_id' => $from->id],
            'new_values' => ['current_stage_id' => $to->id],
            'changed_columns' => ['current_stage_id'],
        ]);

        $row = collect($this->getJson('/api/audit-logs?resource=applicants&source=people&per_page=100')
            ->assertOk()
            ->json('data'))
            ->firstWhere('id', $audit->id);

        $this->assertNotNull($row);
        $change = $row['changes'][0];
        $this->assertSame('currentStageId', $change['field']);
        $this->assertSame('Stage', $change['field_label']);
        $this->assertSame('University Selection', $change['before']);
        $this->assertSame('University Application', $change['after']);
        $this->assertSame('University Selection', $change['old_label']);
        $this->assertSame('University Application', $change['new_label']);
        $this->assertStringNotContainsString('Other information', json_encode($row));
    }

    public function test_system_rows_can_be_separated_from_human_changes(): void
    {
        $admin = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($admin, ['access']);
        $applicant = Applicant::factory()->create(['name' => 'Noor Ltd']);
        $human = $this->audit($applicant, $admin);
        $system = $this->audit($applicant, null, ['subject_label' => 'Seeded history']);

        $humanRows = $this->getJson('/api/audit-logs?resource=applicants&event=updated&source=people&per_page=100')
            ->assertOk()->json('data');
        $systemRows = $this->getJson('/api/audit-logs?resource=applicants&event=updated&source=system&per_page=100')
            ->assertOk()->json('data');

        $this->assertSame([$human->id], collect($humanRows)->pluck('id')->all());
        $this->assertSame([$system->id], collect($systemRows)->pluck('id')->all());
    }

    public function test_detail_includes_actor_ip_method_and_timestamp(): void
    {
        $admin = User::factory()->create(['roles' => ['super_admin']]);
        Sanctum::actingAs($admin, ['access']);
        $applicant = Applicant::factory()->create();
        $audit = $this->audit($applicant, $admin);

        $this->getJson("/api/audit-logs/{$audit->id}")
            ->assertOk()
            ->assertJsonPath('data.changed_by.id', $admin->id)
            ->assertJsonPath('data.ip_address', '203.0.113.7')
            ->assertJsonPath('data.method', 'PATCH')
            ->assertJsonPath('data.created_at', $audit->created_at->toISOString());
    }

    public function test_stage_notification_names_the_applicant_stages_and_actor(): void
    {
        $actor = User::factory()->create(['roles' => ['super_admin']]);
        $recipient = User::factory()->create();
        Employee::factory()->create(['user_id' => $recipient->id, 'status' => 'active']);
        $from = $this->stage('readability_notify_from', 'University Selection');
        $to = $this->stage('readability_notify_to', 'University Application');
        $responsible = Employee::query()->where('user_id', $recipient->id)->firstOrFail();
        $applicant = Applicant::factory()->create([
            'name' => 'Ahmad Wali',
            'responsible_employee_id' => $responsible->id,
        ]);
        $history = ApplicantStageHistory::create([
            'applicant_id' => $applicant->id,
            'old_stage_id' => $from->id,
            'new_stage_id' => $to->id,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);

        app(EducationalWorkflowNotificationService::class)
            ->applicantStageChanged($applicant, $to, $history, $actor->id);

        $notification = Notification::query()
            ->where('notification_key', "applicant.stage.{$history->id}.staff")
            ->firstOrFail();

        $this->assertStringContainsString('Ahmad Wali', $notification->message);
        $this->assertStringContainsString('University Selection', $notification->message);
        $this->assertStringContainsString('University Application', $notification->message);
        $this->assertStringContainsString($actor->name, $notification->message);
        $this->assertSame('University Selection', $notification->data['from_stage']);
        $this->assertSame('University Application', $notification->data['to_stage']);
        $this->assertSame($actor->name, $notification->data['actor']);
    }
}

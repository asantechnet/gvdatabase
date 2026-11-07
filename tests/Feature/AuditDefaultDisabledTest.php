<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\AuditLog;
use App\Models\AuditResourceSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditDefaultDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_audit_resource_starts_disabled_and_model_changes_are_not_audited(): void
    {
        $this->assertSame(0, AuditResourceSetting::query()->where('enabled', true)->count());
        $this->assertGreaterThan(0, AuditResourceSetting::query()->count());

        Applicant::factory()->create(['name' => 'Unaudited applicant']);

        $this->assertSame(0, AuditLog::query()->where('subject_type', 'applicants')->count());
    }
}

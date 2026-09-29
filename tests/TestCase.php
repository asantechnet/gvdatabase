<?php

namespace Tests;

use App\Models\Applicant;
use App\Models\ApplicantAttachment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }

    /**
     * Satisfy the business rule that contracts and invoices require at least
     * one document on the applicant case.
     */
    protected function seedApplicantDocuments(Applicant|int $applicant, int $count = 1): void
    {
        $applicant = $applicant instanceof Applicant ? $applicant : Applicant::findOrFail($applicant);

        for ($i = 1; $i <= $count; $i++) {
            ApplicantAttachment::create([
                'applicant_id' => $applicant->id,
                'label' => "Test document {$i}",
                'category' => 'identity',
                'disk' => 'local',
                'path' => "applicant-attachments/{$applicant->id}/test-doc-{$i}.pdf",
                'original_name' => "test-doc-{$i}.pdf",
                'mime_type' => 'application/pdf',
                'size' => 1024,
            ]);
        }
    }
}

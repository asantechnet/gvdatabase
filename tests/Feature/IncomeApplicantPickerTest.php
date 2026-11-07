<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IncomeApplicantPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_can_search_minimal_applicant_identity_without_general_applicant_access(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'finance_officer')->firstOrFail());
        Sanctum::actingAs($user, ['access']);
        $this->assertFalse($user->hasPermission('view_applicants'));
        Applicant::factory()->count(21)->create();
        $applicant = Applicant::factory()->create(['name' => 'Payment Picker Subject', 'contact' => '0700123456']);
        $archived = Applicant::factory()->create(['name' => 'Archived Payment Picker Subject']);
        $archived->delete();

        $this->assertCount(20, $this->getJson('/api/incomes/applicants')->assertOk()->json('data'));
        $result = $this->getJson('/api/incomes/applicants?search=Payment%20Picker%20Subject')->assertOk()->json('data');
        $this->assertCount(1, $result);
        $this->assertSame(['id', 'name', 'contact'], array_keys($result[0]));
        $this->assertSame($applicant->id, $result[0]['id']);
        $this->getJson('/api/incomes/applicants?search=0700123456')->assertOk()->assertJsonPath('data.0.id', $applicant->id);
        $this->getJson('/api/incomes/applicants?search='.$applicant->id)->assertOk()->assertJsonFragment(['id' => $applicant->id]);
        $this->getJson('/api/incomes/applicants?search='.str_repeat('x', 256))->assertUnprocessable();
    }

    public function test_applicant_picker_requires_income_view_permission(): void
    {
        // Staff now hold a finance read scope to record and review their own
        // drafts; a role with no finance grant at all must still be refused.
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'viewer')->firstOrFail());
        Sanctum::actingAs($user, ['access']);
        $this->getJson('/api/incomes/applicants')->assertForbidden();
    }
}

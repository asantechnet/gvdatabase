<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PositionManagementTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();
        Currency::factory()->create(['is_base' => true, 'rate' => 1]);
        $this->currency = Currency::factory()->create(['is_base' => false, 'rate' => 70]);
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    public function test_positions_are_managed_without_role_or_permission_fields(): void
    {
        $created = $this->postJson('/api/positions', [
            'title' => 'Support Specialist',
            'description' => 'Helps applicants resolve technical issues.',
            'salary' => 42000,
            'currency_id' => $this->currency->id,
            'rate' => 71.25,
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Support Specialist')
            ->assertJsonPath('data.employee_count', 0)
            ->assertJsonPath('data.user_count', 0)
            ->assertJsonPath('data.currency.code', $this->currency->code)
            ->assertJsonPath('data.currency.rate', 71.25)
            ->assertJsonMissingPath('data.permissions')
            ->assertJsonMissingPath('data.is_sys_user');

        $position = Position::findOrFail($created->json('data.id'));
        $this->assertSame(71.25, $this->currency->fresh()->rate);

        $this->putJson("/api/positions/{$position->id}", [
            'title' => 'Applicant Support Specialist',
            'status' => 'inactive',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Applicant Support Specialist')
            ->assertJsonPath('data.status', 'inactive');

        // The list is alphabetical and other positions are seeded (e.g. the
        // migration-seeded "Admission"), so assert the update is listed rather
        // than that it sorts first.
        $this->getJson('/api/positions')->assertOk()
            ->assertJsonFragment(['id' => $position->id, 'title' => 'Applicant Support Specialist']);

        $this->deleteJson("/api/positions/{$position->id}")->assertOk();
        $this->assertSoftDeleted('positions', ['id' => $position->id]);
    }

    public function test_an_assigned_position_cannot_be_deleted(): void
    {
        $position = Position::factory()->create();
        $position->users()->attach(User::factory()->create());

        $this->deleteJson("/api/positions/{$position->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'business.position_in_use');

        $this->assertDatabaseHas('positions', ['id' => $position->id]);
    }
}

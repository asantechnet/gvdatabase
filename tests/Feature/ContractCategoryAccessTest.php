<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\ContractType;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\ServiceBoard;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractCategoryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
    }

    public function test_staff_sees_all_non_employment_categories_but_not_employment(): void
    {
        $staff = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $customer = ContractType::query()->where('slug', 'customer')->firstOrFail();
        $employment = ContractType::query()->where('slug', 'employment')->firstOrFail();
        $other = ContractType::query()->where('slug', 'other')->firstOrFail();

        $scholarship = Contract::factory()->create([
            'contract_type_id' => $customer->id,
            'service' => 'scholarship',
        ]);
        $otherContract = Contract::factory()->create([
            'contract_type_id' => $other->id,
            'service' => null,
        ]);
        $employmentContract = Contract::factory()->create([
            'contract_type_id' => $employment->id,
            'service' => null,
        ]);

        Sanctum::actingAs($staff, ['access']);
        $visible = collect($this->getJson('/api/contracts?per_page=100')->assertOk()->json('data'))->pluck('id');

        $this->assertContains($scholarship->id, $visible);
        $this->assertContains($otherContract->id, $visible);
        $this->assertNotContains($employmentContract->id, $visible);

        $this->getJson("/api/contracts/{$scholarship->id}")->assertOk();
        $this->getJson("/api/contracts/{$employmentContract->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'auth.forbidden');
    }

    public function test_manager_can_read_employment_contracts(): void
    {
        $manager = User::factory()->create(['roles' => ['manager'], 'acc_status' => 'active']);
        $employment = ContractType::query()->where('slug', 'employment')->firstOrFail();
        $contract = Contract::factory()->create([
            'contract_type_id' => $employment->id,
            'service' => null,
        ]);

        Sanctum::actingAs($manager, ['access']);
        $this->getJson('/api/contracts?per_page=100')
            ->assertOk()
            ->assertJsonFragment(['id' => $contract->id]);
    }

    public function test_service_board_access_follows_the_service_contract_permission(): void
    {
        $staff = User::factory()->create(['roles' => ['staff'], 'acc_status' => 'active']);
        $boardOnlyRole = Role::create(['name' => 'board_only']);
        RolePermission::create([
            'role_id' => $boardOnlyRole->id,
            'permission_id' => Permission::query()->where('name', 'view_assigned_boards')->firstOrFail()->id,
        ]);
        $viewer = User::factory()->create(['roles' => ['board_only'], 'acc_status' => 'active']);
        $scholarshipBoard = ServiceBoard::firstOrCreateFor('scholarship');

        Sanctum::actingAs($staff, ['access']);
        $staffBoards = collect($this->getJson('/api/kanban')->assertOk()->json('data'))->pluck('id');
        $this->assertContains((string) $scholarshipBoard->id, $staffBoards);

        Sanctum::actingAs($viewer, ['access']);
        $viewerBoards = collect($this->getJson('/api/kanban')->assertOk()->json('data'))->pluck('id');
        $this->assertNotContains((string) $scholarshipBoard->id, $viewerBoards);

        // Board definitions are read-only now.
        Sanctum::actingAs($staff, ['access']);
        $this->postJson('/api/boards', ['title' => 'New board'])->assertStatus(405);
        $this->putJson("/api/boards/{$scholarshipBoard->id}", ['title' => 'Renamed'])->assertNotFound();
    }

    public function test_contract_category_permissions_are_seeded_as_approved(): void
    {
        $staff = User::factory()->create(['roles' => ['staff']]);
        $manager = User::factory()->create(['roles' => ['manager']]);
        $viewer = User::factory()->create(['roles' => ['viewer']]);

        foreach ([$staff, $viewer] as $employee) {
            $this->assertTrue($employee->hasPermission('view_contracts'));
            foreach (['private', 'scholarship', 'test_offer', 'academy', 'other'] as $category) {
                $this->assertTrue($employee->hasPermission("view_{$category}_contracts"));
            }
        }
        $this->assertFalse($staff->hasPermission('view_employment_contracts'));
        $this->assertFalse($viewer->hasPermission('view_employment_contracts'));
        $this->assertTrue($manager->hasPermission('view_employment_contracts'));
        $this->assertTrue($manager->hasPermission('view_private_contracts'));
    }
}

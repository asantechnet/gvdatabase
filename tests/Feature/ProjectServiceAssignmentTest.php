<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectServiceAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_tracks_only_the_customer_services_selected_for_it(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create();
        $customer = Customer::factory()->create();
        $design = Service::factory()->create(['type' => 'Brand design', 'currency_id' => $currency->id]);
        $hosting = Service::factory()->create(['type' => 'Website hosting', 'currency_id' => $currency->id]);

        CustomerService::query()->create(['customer_id' => $customer->id, 'service_id' => $design->id]);
        CustomerService::query()->create(['customer_id' => $customer->id, 'service_id' => $hosting->id]);

        $projectId = $this->postJson('/api/projects', [
            'name' => 'Website relaunch',
            'customer_id' => $customer->id,
            'service_ids' => [$hosting->id, $design->id],
        ])->assertCreated()
            ->assertJsonPath('data.service_ids', [$design->id, $hosting->id])
            ->assertJsonPath('data.services.0.type', 'Brand design')
            ->assertJsonPath('data.services.1.type', 'Website hosting')
            ->json('data.id');

        $this->assertDatabaseHas('project_service', ['project_id' => $projectId, 'service_id' => $design->id]);
        $this->assertDatabaseHas('project_service', ['project_id' => $projectId, 'service_id' => $hosting->id]);

        $this->putJson("/api/projects/{$projectId}", ['service_ids' => [$hosting->id]])
            ->assertOk()
            ->assertJsonPath('data.service_ids', [$hosting->id]);

        $this->assertDatabaseMissing('project_service', ['project_id' => $projectId, 'service_id' => $design->id]);
        $this->assertDatabaseHas('project_service', ['project_id' => $projectId, 'service_id' => $hosting->id]);
    }

    public function test_a_project_cannot_be_linked_to_a_service_the_customer_has_not_selected(): void
    {
        Sanctum::actingAs($this->superAdmin(), ['access']);
        $currency = Currency::factory()->create();
        $customer = Customer::factory()->create();
        $otherCustomer = Customer::factory()->create();
        $service = Service::factory()->create(['currency_id' => $currency->id]);
        CustomerService::query()->create(['customer_id' => $otherCustomer->id, 'service_id' => $service->id]);

        $this->postJson('/api/projects', [
            'name' => 'Invalid delivery scope',
            'customer_id' => $customer->id,
            'service_ids' => [$service->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('service_ids');

        $this->assertDatabaseMissing('projects', ['name' => 'Invalid delivery scope']);
        $this->assertDatabaseCount('project_service', 0);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['acc_status' => 'active']);
        $user->roles()->attach(Role::query()->firstOrCreate(['name' => 'super_admin']));

        return $user;
    }
}

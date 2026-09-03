<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerServiceController;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerServiceAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_service_inherits_customer_marketer_and_keeps_delivery_employee_separate(): void
    {
        $service = Service::factory()->create();
        $marketer = $this->marketerEmployee();
        $deliveryEmployee = Employee::factory()->create(['employment_type' => 'commission']);
        $deliveryEmployee->commissionServices()->attach($service->id, ['percentage' => 10]);
        $customer = Customer::factory()->create(['marketer_id' => $marketer->id]);

        $request = Request::create("/api/customers/{$customer->id}/services", 'POST', [
            'services' => [[
                'service_id' => $service->id,
                'employee_id' => $deliveryEmployee->id,
                'status' => 'active',
            ]],
        ]);

        $payload = app(CustomerServiceController::class)
            ->store($request, $customer)
            ->response()
            ->getData(true);

        $this->assertDatabaseHas('customer_service', [
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'employee_id' => $deliveryEmployee->id,
            'marketer_id' => $marketer->id,
        ]);
        $this->assertSame($deliveryEmployee->id, $payload['data'][0]['employee_id']);
        $this->assertSame($deliveryEmployee->name, $payload['data'][0]['employee']['name']);
        $this->assertSame($marketer->id, $payload['data'][0]['marketer_id']);
        $this->assertSame($marketer->name, $payload['data'][0]['marketer']['name']);
    }

    public function test_customer_service_can_override_or_clear_the_inherited_marketer(): void
    {
        $customerMarketer = $this->marketerEmployee();
        $replacementMarketer = $this->marketerEmployee();
        $customer = Customer::factory()->create(['marketer_id' => $customerMarketer->id]);
        $firstService = Service::factory()->create();
        $secondService = Service::factory()->create();

        $request = Request::create("/api/customers/{$customer->id}/services", 'POST', [
            'services' => [
                [
                    'service_id' => $firstService->id,
                    'marketer_id' => $replacementMarketer->id,
                    'status' => 'active',
                ],
                [
                    'service_id' => $secondService->id,
                    'marketer_id' => null,
                    'status' => 'active',
                ],
            ],
        ]);

        app(CustomerServiceController::class)->store($request, $customer);

        $this->assertDatabaseHas('customer_service', [
            'customer_id' => $customer->id,
            'service_id' => $firstService->id,
            'marketer_id' => $replacementMarketer->id,
        ]);
        $this->assertDatabaseHas('customer_service', [
            'customer_id' => $customer->id,
            'service_id' => $secondService->id,
            'marketer_id' => null,
        ]);
    }

    public function test_delivery_owner_must_have_the_selected_service_capability(): void
    {
        $customer = Customer::factory()->create();
        $service = Service::factory()->create();
        $ineligibleEmployee = Employee::factory()->create(['employment_type' => 'commission']);
        $request = Request::create("/api/customers/{$customer->id}/services", 'POST', [
            'services' => [[
                'service_id' => $service->id,
                'employee_id' => $ineligibleEmployee->id,
                'status' => 'active',
            ]],
        ]);

        try {
            app(CustomerServiceController::class)->store($request, $customer);
            $this->fail('A delivery employee without the selected service capability was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('services.0.employee_id', $exception->errors());
        }
    }

    private function marketerEmployee(): Employee
    {
        $position = Position::query()->firstOrCreate(
            ['system_key' => 'marketer'],
            ['title' => 'Marketer', 'description' => 'CRM ownership', 'salary' => 0, 'status' => 'active'],
        );

        return Employee::factory()->create(['position_id' => $position->id]);
    }
}

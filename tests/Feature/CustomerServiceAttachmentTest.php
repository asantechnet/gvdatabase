<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerServiceController;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CustomerServiceAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiple_services_can_be_attached_and_returned_with_their_relations(): void
    {
        $customer = Customer::factory()->create();
        $currency = Currency::factory()->create();
        $services = Service::factory()->count(2)->create(['currency_id' => $currency->id]);
        $request = Request::create('/api/customers/'.$customer->id.'/services', 'POST', [
            'services' => $services->map(fn (Service $service) => [
                'service_id' => $service->id,
                'deadline' => '2026-12-31',
                'status' => 'active',
            ])->all(),
        ]);
        $resource = app(CustomerServiceController::class)->store($request, $customer);
        $payload = $resource->response()->getData(true);

        $this->assertCount(2, $payload['data']);
        $this->assertSame($services->pluck('id')->sort()->values()->all(), collect($payload['data'])->pluck('service_id')->sort()->values()->all());
        $this->assertSame($currency->code, $payload['data'][0]['currency']['code']);
        $this->assertDatabaseCount('customer_service', 2);
    }
}

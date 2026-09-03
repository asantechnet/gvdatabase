<?php

namespace Tests\Feature;

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\VisitorController;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Lead;
use App\Models\Position;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmMarketerAttributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketer_users_own_new_crm_records_and_cannot_spoof_another_marketer(): void
    {
        [$user, $marketer, $otherMarketer] = $this->marketers();

        app(CustomerController::class)->store($this->request($user, '/api/customers', [
            'name' => 'Customer contact', 'company' => 'Customer Co', 'contact' => '+93700000000',
            'date' => '2026-09-03', 'marketer_id' => $otherMarketer->id, 'marketer' => $otherMarketer->name,
        ]));
        app(LeadController::class)->store($this->request($user, '/api/leads', [
            'name' => 'Lead contact', 'company' => 'Lead Co', 'contact' => '+93700000001',
            'marketer_id' => $otherMarketer->id, 'marketer' => $otherMarketer->name,
        ]));
        app(VisitorController::class)->store($this->request($user, '/api/visitors', [
            'name' => 'Visitor contact', 'company' => 'Visitor Co', 'contact' => '+93700000002',
            'service' => 'Consulting', 'date' => '2026-09-03',
            'marketer_id' => $otherMarketer->id, 'marketer' => $otherMarketer->name,
        ]));

        foreach ([Customer::class, Lead::class, Visitor::class] as $model) {
            $record = $model::query()->firstOrFail();
            $this->assertSame($marketer->id, $record->marketer_id);
            $this->assertSame($marketer->name, $record->marketer);
        }

        $customer = Customer::query()->firstOrFail();
        app(CustomerController::class)->update($this->request($user, "/api/customers/{$customer->id}", [
            'marketer_id' => $otherMarketer->id,
        ], 'PUT'), $customer);

        $this->assertSame($marketer->id, $customer->fresh()->marketer_id);
    }

    public function test_attribution_survives_visitor_to_lead_to_customer_conversion(): void
    {
        [, $marketer] = $this->marketers();
        $visitor = Visitor::create([
            'name' => 'Conversion contact', 'company' => 'Conversion Co', 'contact' => '+93700000003',
            'service' => 'Implementation', 'marketer' => $marketer->name, 'marketer_id' => $marketer->id,
            'date' => '2026-09-03',
        ]);
        $owner = User::factory()->create();

        app(LeadController::class)->fromVisitor($this->request($owner, "/api/visitors/{$visitor->id}/convert-to-lead", [], 'POST'), $visitor);
        $lead = Lead::query()->where('visitor_id', $visitor->id)->firstOrFail();
        app(LeadController::class)->convert($lead);
        $customer = Customer::query()->where('contact', $visitor->contact)->firstOrFail();

        $this->assertSame($marketer->id, $lead->marketer_id);
        $this->assertSame($marketer->name, $lead->marketer);
        $this->assertSame($marketer->id, $customer->marketer_id);
        $this->assertSame($marketer->name, $customer->marketer);
    }

    public function test_an_explicit_marketer_must_be_a_marketer_employee(): void
    {
        $user = User::factory()->create();
        $nonMarketer = Employee::factory()->create(['name' => 'Operations employee']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(CustomerController::class)->store($this->request($user, '/api/customers', [
            'name' => 'Customer contact', 'company' => 'Customer Co', 'contact' => '+93700000000',
            'date' => '2026-09-03', 'marketer_id' => $nonMarketer->id,
        ]));
    }

    public function test_legacy_marketer_label_is_upgraded_to_the_matching_employee_id(): void
    {
        [, $marketer] = $this->marketers();
        $user = User::factory()->create();

        app(CustomerController::class)->store($this->request($user, '/api/customers', [
            'name' => 'Customer contact', 'company' => 'Customer Co', 'contact' => '+93700000000',
            'date' => '2026-09-03', 'marketer' => $marketer->name,
        ]));

        $customer = Customer::query()->firstOrFail();
        $this->assertSame($marketer->id, $customer->marketer_id);
        $this->assertSame($marketer->name, $customer->marketer);
    }

    public function test_authenticated_account_exposes_current_marketer_employee_context(): void
    {
        [$user, $marketer] = $this->marketers();
        Sanctum::actingAs($user, ['access']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.employee.id', $marketer->id)
            ->assertJsonPath('data.employee.position_id', $marketer->position_id)
            ->assertJsonPath('data.employee.is_marketer', true)
            ->assertJsonPath('data.is_marketer', true);
    }

    /** @return array{User, Employee, Employee} */
    private function marketers(): array
    {
        $position = Position::query()->firstOrCreate(
            ['system_key' => 'marketer'],
            Position::factory()->make(['title' => 'Marketer', 'system_key' => 'marketer'])->getAttributes(),
        );
        $user = User::factory()->create();
        $marketer = Employee::factory()->create([
            'user_id' => $user->id,
            'position_id' => $position->id,
            'name' => 'Primary marketer',
        ]);
        $otherMarketer = Employee::factory()->create([
            'position_id' => $position->id,
            'name' => 'Other marketer',
        ]);

        return [$user, $marketer, $otherMarketer];
    }

    /** @param array<string, mixed> $payload */
    private function request(User $user, string $uri, array $payload, string $method = 'POST'): Request
    {
        $request = Request::create($uri, $method, $payload);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}

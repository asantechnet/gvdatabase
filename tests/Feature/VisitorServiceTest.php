<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitorServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'super_admin']));
        Sanctum::actingAs($user, ['access']);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Service Visitor',
            'contact' => '0700000100',
            'service' => 'scholarship',
            'date' => now()->toDateString(),
            ...$overrides,
        ];
    }

    public function test_unsupported_service_values_are_rejected_with_a_field_error(): void
    {
        $before = Visitor::query()->count();

        foreach (['Scholarship', 'consulting', 'test offer', ''] as $service) {
            $this->postJson('/api/visitors', $this->payload(['service' => $service]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['service']);
        }

        $this->assertSame($before, Visitor::query()->count());
    }

    public function test_a_canonical_service_is_returned_with_its_translation_key(): void
    {
        $created = $this->postJson('/api/visitors', $this->payload(['service' => 'test_offer']))
            ->assertCreated()
            ->assertJsonPath('data.service', 'test_offer')
            ->assertJsonPath('data.service_key', 'test_offer')
            ->assertJsonPath('data.service_label_key', 'educational.builtInServices.testOffer')
            ->json('data');

        $this->assertSame('Test Offer', $created['service_label']);
        $this->assertDatabaseHas('visitors', ['id' => $created['id'], 'service' => 'test_offer']);
    }

    public function test_update_also_rejects_a_non_built_in_service(): void
    {
        $visitor = Visitor::create($this->payload(['service' => 'academy']));

        $this->putJson("/api/visitors/{$visitor->id}", ['service' => 'Academy'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service']);
        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'service' => 'academy']);

        $this->putJson("/api/visitors/{$visitor->id}", ['service' => 'private'])
            ->assertOk()
            ->assertJsonPath('data.service', 'private')
            ->assertJsonPath('data.service_label_key', 'educational.builtInServices.private');
    }

    public function test_normalization_maps_known_labels_and_keeps_unknown_values(): void
    {
        $label = Visitor::create($this->payload(['name' => 'Label case', 'service' => 'Scholarship']));
        $spaced = Visitor::create($this->payload(['name' => 'Spaced label', 'service' => 'Test Offer']));
        $unknown = Visitor::create($this->payload(['name' => 'Unknown service', 'service' => 'Consulting']));

        $migration = require database_path('migrations/2026_11_01_000003_normalize_visitor_services.php');
        $migration->up();

        $this->assertDatabaseHas('visitors', ['id' => $label->id, 'service' => 'scholarship', 'legacy_service' => 'Scholarship']);
        $this->assertDatabaseHas('visitors', ['id' => $spaced->id, 'service' => 'test_offer', 'legacy_service' => 'Test Offer']);
        // Unknown text is preserved with its original value, never coerced.
        $this->assertDatabaseHas('visitors', ['id' => $unknown->id, 'service' => 'Consulting', 'legacy_service' => 'Consulting']);

        $row = collect($this->getJson('/api/visitors')->assertOk()->json('data'))->firstWhere('id', $label->id);
        $this->assertSame('scholarship', $row['service_key']);
        $this->assertSame('educational.builtInServices.scholarship', $row['service_label_key']);

        $unknownRow = collect($this->getJson('/api/visitors')->assertOk()->json('data'))->firstWhere('id', $unknown->id);
        $this->assertNull($unknownRow['service_key']);
        $this->assertSame('Consulting', $unknownRow['service_label']);
    }
}

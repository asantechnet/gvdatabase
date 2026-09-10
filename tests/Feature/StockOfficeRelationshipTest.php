<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockOfficeRelationshipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);
    }

    public function test_asset_uses_an_office_foreign_key_and_exposes_the_relationship(): void
    {
        $office = Office::factory()->create(['name' => 'Central Office', 'code' => 'CENTRAL']);

        $response = $this->postJson('/api/assets', [
            'serial_number' => 'REL-1001',
            'item' => 'Laptop',
            'quantity' => 1,
            'price' => 900,
            'model' => 'ThinkPad',
            'condition' => 'good',
            'office_id' => $office->id,
            'staff_id' => null,
        ])->assertCreated()
            ->assertJsonPath('data.office_id', $office->id)
            ->assertJsonPath('data.office.id', $office->id)
            ->assertJsonPath('data.office.name', 'Central Office');

        $assetId = $response->json('data.id');
        $this->assertDatabaseHas('assets', ['id' => $assetId, 'office_id' => $office->id]);

        $this->getJson('/api/assets?office_id='.$office->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_office_renames_flow_through_asset_and_assigned_offices_cannot_be_deleted(): void
    {
        $office = Office::factory()->create(['name' => 'Old Office', 'code' => 'OLD']);
        $asset = Asset::factory()->for($office)->create();

        $this->putJson('/api/offices/'.$office->id, [
            'name' => 'Renamed Office',
            'code' => 'RENAMED',
            'email' => $office->email,
            'phone' => $office->phone,
            'address' => $office->address,
            'status' => 'active',
            'latitude' => null,
            'longitude' => null,
            'geofence_radius_meters' => 100,
            'geofence_enabled' => false,
        ])->assertOk();

        $this->getJson('/api/assets/'.$asset->id)
            ->assertOk()
            ->assertJsonPath('data.office.id', $office->id)
            ->assertJsonPath('data.office.name', 'Renamed Office');

        $this->deleteJson('/api/offices/'.$office->id)->assertConflict();
    }

    public function test_asset_rejects_an_unknown_office(): void
    {
        $this->postJson('/api/assets', [
            'serial_number' => 'REL-INVALID',
            'item' => 'Monitor',
            'quantity' => 1,
            'price' => 250,
            'model' => 'Display',
            'condition' => 'new',
            'office_id' => 999999,
        ])->assertUnprocessable()->assertJsonValidationErrors('office_id');
    }
}

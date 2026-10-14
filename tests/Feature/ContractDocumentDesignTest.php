<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractDocumentDesign;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Language;
use App\Models\Role;
use App\Models\User;
use App\Services\ContractDocumentDesignService;
use App\Services\Contracts\BuiltInContractTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesBuiltInContracts;
use Tests\TestCase;

/**
 * Artwork scoping rules (contract-template-sanifier.md §3):
 * customer contracts may narrow the design to a service, service designs win
 * over the customer-wide design, employment/other contracts share one design,
 * and the print layer always receives the resolved print-safe area.
 */
class ContractDocumentDesignTest extends TestCase
{
    use CreatesBuiltInContracts, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('name', 'super_admin')->firstOrFail());
        Sanctum::actingAs($user, ['access']);
    }

    public function test_customer_contracts_accept_service_scoped_artwork_while_other_types_reject_it(): void
    {
        $customer = $this->type('customer');
        $employment = $this->type('employment');
        $service = 'academy';

        $this->post('/api/contract-document-designs', [
            'contract_type_id' => $customer->id,
            'service' => $service,
            'letterhead' => $this->fakePng('letterhead.png'),
            'safe_x' => 12.5,
            'safe_y' => 20,
            'safe_width' => 70,
            'safe_height' => 61,
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.service', $service)
            ->assertJsonPath('data.safe_width', 70);

        // Employment and other contracts carry exactly one design per type.
        $this->postJson('/api/contract-document-designs', [
            'contract_type_id' => $employment->id,
            'service' => $service,
        ])->assertStatus(422)->assertJsonValidationErrors('service');

        $this->postJson('/api/contract-document-designs', ['contract_type_id' => $employment->id])
            ->assertCreated()
            ->assertJsonPath('data.service', null);
    }

    public function test_builtin_contract_snapshots_type_artwork_without_a_catalogue_binding(): void
    {
        $customer = $this->type('customer');
        $currency = Currency::factory()->create(['rate' => 1]);
        $language = Language::create(['name' => 'English', 'code' => 'en']);
        $template = app(BuiltInContractTemplateService::class)->ensure('academy');
        $template->versions()->create([
            'language_id' => $language->id, 'version_number' => 1, 'name' => 'Terms',
            'status' => 'active', 'is_current' => true,
            'current_scope_key' => "{$template->id}:0:{$language->id}", 'content' => '<p>Terms</p>',
        ]);
        $typeDesign = $this->design([
            'contract_type_id' => $customer->id, 'letterhead' => 'contract-designs/customer.png',
            'cover_design' => 'contract-designs/customer-cover.png', 'safe_x' => 11.25, 'safe_height' => 55,
        ]);
        $this->design(['contract_type_id' => $customer->id, 'service' => 'scholarship', 'letterhead' => 'contract-designs/unrelated.png']);
        $id = $this->postJson('/api/contracts/academy', [
            'name' => 'Artwork applicant', 'fee' => 100, 'discount' => 0, 'currency_id' => $currency->id,
            'currency_rate' => 1, 'language_id' => $language->id, 'start_date' => now()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.snapshot_data.document_design.id', $typeDesign->id)->json('data.id');
        $typeDesign->update(['safe_x' => 50, 'letterhead' => 'contract-designs/revised.png']);
        $this->getJson('/api/contracts/'.$id)->assertOk()
            ->assertJsonPath('data.snapshot_data.document_design.letterhead', 'contract-designs/customer.png')
            ->assertJsonPath('data.snapshot_data.document_design.cover_design', 'contract-designs/customer-cover.png')
            ->assertJsonPath('data.snapshot_data.document_design.safe_x', 11.25)
            ->assertJsonPath('data.snapshot_data.document_design.safe_height', 55);
    }

    public function test_builtin_service_artwork_overrides_type_artwork_and_stays_frozen(): void
    {
        $customer = $this->type('customer');
        $this->design(['contract_type_id' => $customer->id, 'letterhead' => 'contract-designs/default.png']);
        $design = $this->design(['contract_type_id' => $customer->id, 'service' => 'academy', 'letterhead' => 'contract-designs/academy.png', 'safe_x' => 12.5]);
        $created = $this->createBuiltInContract('academy');
        $this->assertSame($design->id, $created['snapshot_data']['document_design']['id']);
        $design->update(['letterhead' => 'contract-designs/revised.png', 'safe_x' => 20]);
        $this->getJson('/api/contracts/'.$created['id'])->assertOk()
            ->assertJsonPath('data.snapshot_data.document_design.letterhead', 'contract-designs/academy.png')
            ->assertJsonPath('data.snapshot_data.document_design.safe_x', 12.5);
    }

    public function test_employment_and_other_contracts_share_the_single_type_design(): void
    {
        $employment = $this->type('employment');
        $employmentDesign = $this->design([
            'contract_type_id' => $employment->id,
            'letterhead' => 'contract-designs/employment.png',
            'safe_x' => 9.5,
            'safe_y' => 16.5,
            'safe_width' => 81,
            'safe_height' => 72,
        ]);
        // A different type's design must never leak into employment contracts.
        $this->design(['contract_type_id' => $this->type('customer')->id, 'letterhead' => 'contract-designs/customer.png']);

        $contract = Contract::factory()->create([
            'template_id' => null,
            'contract_type_id' => $employment->id,
            'snapshot_data' => ['entity' => []],
        ]);

        $this->getJson("/api/contracts/{$contract->id}")
            ->assertOk()
            ->assertJsonPath('data.snapshot_data.document_design.id', $employmentDesign->id)
            ->assertJsonPath('data.snapshot_data.document_design.letterhead', 'contract-designs/employment.png')
            ->assertJsonPath('data.snapshot_data.document_design.safe_width', 81);
    }

    public function test_a_contract_without_a_design_snapshot_resolves_the_current_design_when_read(): void
    {
        $customer = $this->type('customer');
        $service = 'academy';
        $serviceDesign = $this->design([
            'contract_type_id' => $customer->id,
            'service' => $service,
            'letterhead' => 'contract-designs/service.png',
            'safe_x' => 14.75,
        ]);

        $contract = Contract::factory()->create([
            'template_id' => null,
            'contract_type_id' => $customer->id,
            'service' => $service,
            'snapshot_data' => ['entity' => []],
        ]);

        $this->getJson("/api/contracts/{$contract->id}")
            ->assertOk()
            ->assertJsonPath('data.snapshot_data.document_design.id', $serviceDesign->id)
            ->assertJsonPath('data.snapshot_data.document_design.letterhead_url', Storage::disk('public')->url('contract-designs/service.png'))
            ->assertJsonPath('data.snapshot_data.document_design.safe_x', 14.75);

        // Reading never rewrites the stored snapshot.
        $this->assertSame(['entity' => []], $contract->fresh()->snapshot_data);
        $this->assertSame($serviceDesign->id, app(ContractDocumentDesignService::class)->resolveForContract($contract->fresh())?->id);
    }

    public function test_replacing_or_removing_design_artwork_deletes_the_superseded_file(): void
    {
        Storage::disk('public')->put('contract-designs/old.png', 'old letterhead');
        $design = $this->design(['contract_type_id' => $this->type('customer')->id, 'letterhead' => 'contract-designs/old.png']);

        $this->post('/api/contract-document-designs/'.$design->id, [
            '_method' => 'PUT',
            'letterhead' => $this->fakePng('new.png'),
            'safe_x' => 10,
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.safe_x', 10);

        Storage::disk('public')->assertMissing('contract-designs/old.png');
        $this->assertNotSame('contract-designs/old.png', $design->fresh()->letterhead);
        Storage::disk('public')->assertExists($design->fresh()->letterhead);

        $this->putJson("/api/contract-document-designs/{$design->id}", ['remove_letterhead' => true])
            ->assertOk()
            ->assertJsonPath('data.letterhead', null);

        $this->assertNull($design->fresh()->letterhead);
    }

    public function test_scoped_artwork_rejects_a_safe_area_that_extends_beyond_the_page(): void
    {
        $type = $this->type('customer');
        $response = $this->postJson('/api/contract-document-designs', [
            'contract_type_id' => $type->id, 'safe_x' => 95, 'safe_width' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors(['safe_x', 'safe_width']);
        $this->assertStringContainsString('100%', $response->json('messages.safe_width.0'));
        $this->assertDatabaseCount('contract_document_designs', 0);
        $this->postJson('/api/contract-document-designs', [
            'contract_type_id' => $type->id, 'safe_x' => 90, 'safe_width' => 10,
        ])->assertCreated()->assertJsonPath('data.safe_width', 10);
    }

    private function type(string $slug): ContractType
    {
        return ContractType::query()->where('slug', $slug)->firstOrFail();
    }

    /** @param array<string, mixed> $attributes */
    private function design(array $attributes): ContractDocumentDesign
    {
        return ContractDocumentDesign::create($attributes);
    }

    private function fakePng(string $name): UploadedFile
    {
        $data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, (string) $data);
    }
}

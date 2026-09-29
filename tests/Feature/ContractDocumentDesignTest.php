<?php

namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\Contract;
use App\Models\ContractDocumentDesign;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Services\ContractDocumentDesignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Artwork scoping rules (contract-template-sanifier.md §3):
 * customer contracts may narrow the design to a service, service designs win
 * over the customer-wide design, employment/other contracts share one design,
 * and the print layer always receives the resolved print-safe area.
 */
class ContractDocumentDesignTest extends TestCase
{
    use RefreshDatabase;

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
        $service = $this->service();

        $this->post('/api/contract-document-designs', [
            'contract_type_id' => $customer->id,
            'service_id' => $service->id,
            'letterhead' => $this->fakePng('letterhead.png'),
            'safe_x' => 12.5,
            'safe_y' => 20,
            'safe_width' => 70,
            'safe_height' => 61,
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.safe_width', 70);

        // Employment and other contracts carry exactly one design per type.
        $this->postJson('/api/contract-document-designs', [
            'contract_type_id' => $employment->id,
            'service_id' => $service->id,
        ])->assertStatus(422)->assertJsonValidationErrors('service_id');

        $this->postJson('/api/contract-document-designs', ['contract_type_id' => $employment->id])
            ->assertCreated()
            ->assertJsonPath('data.service_id', null);
    }

    public function test_customer_contract_snapshot_prefers_the_service_design(): void
    {
        $customer = $this->type('customer');
        $service = $this->service();

        $this->design(['contract_type_id' => $customer->id, 'letterhead' => 'contract-designs/type-wide.png', 'safe_x' => 8.5]);
        $serviceDesign = $this->design([
            'contract_type_id' => $customer->id,
            'service_id' => $service->id,
            'letterhead' => 'contract-designs/service.png',
            'cover_design' => 'contract-designs/service-cover.png',
            'safe_x' => 11.25,
            'safe_y' => 22.5,
            'safe_width' => 66,
            'safe_height' => 55,
        ]);

        $contract = $this->postJson('/api/contracts', [
            'template_id' => $this->template($customer->id, $service->id),
            'applicant_id' => Applicant::factory()->create()->id,
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'service_lines' => [['service_id' => $service->id, 'fee' => 450]],
        ])->assertCreated()->json('data.snapshot_data.document_design');

        $this->assertSame($serviceDesign->id, $contract['id']);
        $this->assertSame('contract-designs/service.png', $contract['letterhead']);
        $this->assertSame('contract-designs/service-cover.png', $contract['cover_design']);
        $this->assertEqualsWithDelta(11.25, (float) $contract['safe_x'], 0.001);
        $this->assertEqualsWithDelta(55.0, (float) $contract['safe_height'], 0.001);
    }

    public function test_customer_contract_without_a_service_snapshot_uses_the_customer_wide_design(): void
    {
        $customer = $this->type('customer');
        $catalogue = $this->service();
        $typeDesign = $this->design(['contract_type_id' => $customer->id, 'letterhead' => 'contract-designs/customer.png', 'safe_y' => 18]);
        $this->design(['contract_type_id' => $customer->id, 'service_id' => $catalogue->id, 'letterhead' => 'contract-designs/service.png']);
        $otherService = $this->service();

        $contract = $this->postJson('/api/contracts', [
            'template_id' => $this->template($customer->id, $otherService->id),
            'applicant_id' => Applicant::factory()->create()->id,
            'status' => 'draft',
            'start_date' => now()->toDateString(),
            'service_lines' => [['service_id' => $otherService->id]],
        ])->assertCreated()->json('data.snapshot_data.document_design');

        $this->assertSame($typeDesign->id, $contract['id']);
        $this->assertSame('contract-designs/customer.png', $contract['letterhead']);
        $this->assertEqualsWithDelta(18.0, (float) $contract['safe_y'], 0.001);
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
        $service = $this->service();
        $serviceDesign = $this->design([
            'contract_type_id' => $customer->id,
            'service_id' => $service->id,
            'letterhead' => 'contract-designs/service.png',
            'safe_x' => 14.75,
        ]);

        $contract = Contract::factory()->create([
            'template_id' => null,
            'contract_type_id' => $customer->id,
            'snapshot_data' => ['entity' => []],
        ]);
        $contract->contractServices()->create(['service_id' => $service->id, 'fee' => 100]);

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

    public function test_package_issued_contracts_snapshot_the_service_design(): void
    {
        $customer = $this->type('customer');
        $currency = Currency::factory()->create(['is_base' => true, 'is_active' => true, 'rate' => 1]);
        $service = Service::factory()->create(['currency_id' => $currency->id, 'fee' => 600]);
        $design = $this->design([
            'contract_type_id' => $customer->id,
            'service_id' => $service->id,
            'letterhead' => 'contract-designs/package.png',
            'safe_x' => 12,
        ]);
        $this->design(['contract_type_id' => $customer->id, 'letterhead' => 'contract-designs/customer.png']);

        $package = $this->postJson('/api/packages', [
            'name' => 'Student Success Bundle',
            'service_ids' => [$service->id],
            'currency_id' => $currency->id,
            'fee' => 600,
        ])->assertCreated()->json('data.id');

        $packageApplicant = Applicant::factory()->create();
        $this->seedApplicantDocuments($packageApplicant);

        $this->postJson("/api/packages/{$package}/issue", [
            'applicant_id' => $packageApplicant->id,
            'currency_id' => $currency->id,
            'fee' => 600,
            'purchased_at' => now()->toDateString(),
        ])->assertCreated();

        $contract = Contract::query()->where('contract_type_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame($design->id, $contract->snapshot_data['document_design']['id']);
        $this->assertEqualsWithDelta(12.0, (float) $contract->snapshot_data['document_design']['safe_x'], 0.001);
    }

    private function type(string $slug): ContractType
    {
        return ContractType::query()->where('slug', $slug)->firstOrFail();
    }

    private function service(): Service
    {
        return Service::factory()->create(['currency_id' => Currency::factory()->create()->id]);
    }

    /** @param array<string, mixed> $attributes */
    private function design(array $attributes): ContractDocumentDesign
    {
        return ContractDocumentDesign::create($attributes);
    }

    private function template(int $typeId, ?int $serviceId): int
    {
        return $this->postJson('/api/contract-templates', [
            'name' => 'Service agreement',
            'contract_type_id' => $typeId,
            'service_id' => $serviceId ?? Service::factory()->create()->id,
            'cover_text' => 'Cover',
            'content' => '<p>Agreement</p>',
        ])->assertCreated()->json('data.id');
    }

    private function fakePng(string $name): UploadedFile
    {
        $data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($name, (string) $data);
    }
}

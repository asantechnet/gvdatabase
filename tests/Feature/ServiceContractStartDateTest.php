<?php

namespace Tests\Feature;

use App\Http\Requests\StoreServiceContractRequest;
use App\Models\Contract;
use App\Models\ContractTemplateVersion;
use App\Models\Country;
use App\Models\Currency;
use App\Models\EducationLevel;
use App\Models\Language;
use App\Models\Subject;
use App\Models\User;
use App\Services\Contracts\BuiltInContractTemplateService;
use App\Support\BuiltInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The service-contract dialog submits no start date; the server derives it.
 *
 * The client schema in `resources/ts/utils/apiValidation.ts` is the only place
 * a browser submission can be rejected before it reaches Laravel, so this test
 * guards both directions: the server accepts a dialog-shaped payload, and no
 * future client-required key contradicts a server `prohibited` rule.
 */
class ServiceContractStartDateTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Language $language;

    private Country $country;

    private ContractTemplateVersion $activeVersion;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->create(['roles' => ['super_admin']]), ['access']);

        Currency::factory()->create(['code' => 'USD', 'rate' => 1, 'is_base' => true]);
        $this->currency = Currency::factory()->create(['code' => 'AFN', 'rate' => 1, 'is_base' => false]);
        $this->language = Language::create(['name' => 'English', 'code' => 'en']);
        $this->country = Country::create(['name' => 'Default destination', 'code' => 'QA']);

        $template = app(BuiltInContractTemplateService::class)->ensure(BuiltInService::SCHOLARSHIP);
        $this->activeVersion = $template->versions()->create([
            'language_id' => $this->language->id,
            'country_id' => $this->country->id,
            'version_number' => 1,
            'name' => 'Agreed terms',
            'status' => 'active',
            'is_current' => true,
            'current_scope_key' => implode(':', [$template->id, $this->country->id, '', $this->language->id]),
            'content' => '<p>{{contract.number}} {{customer.name}} {{service.name}} {{service.fee}} {{service.currency}} {{service.discount}}</p>',
        ]);
    }

    /** The payload the dialog builds for a scholarship contract. */
    private function payload(array $extra = []): array
    {
        $education = EducationLevel::firstOrCreate(['name' => 'Bachelor'], ['is_active' => true]);
        $subject = Subject::firstOrCreate(['name' => 'Mathematics'], ['is_active' => true]);

        return [
            'name' => 'New applicant',
            'father_name' => 'Father',
            'father_phone' => '0701111111',
            'contact' => '0701234567',
            'fee' => 120,
            'discount' => 20,
            'currency_id' => $this->currency->id,
            'currency_rate' => 2.5,
            'language_id' => $this->language->id,
            'contract_template_version_id' => $this->activeVersion->id,
            'grade_score' => 80,
            'country_id' => $this->country->id,
            'education_level_id' => $education->id,
            'favorite_subject_ids' => [$subject->id],
            'application_fee' => 120,
            ...$extra,
        ];
    }

    public function test_a_dialog_shaped_payload_is_accepted_and_the_server_derives_the_start_date(): void
    {
        $payload = $this->payload();

        $this->assertArrayNotHasKey('start_date', $payload, 'The dialog must not send a start date.');

        $this->postJson('/api/contracts/scholarship', $payload)->assertSuccessful();

        $contract = Contract::query()->latest('id')->firstOrFail();

        $this->assertSame(now()->toDateString(), $contract->start_date->toDateString());
    }

    public function test_a_client_supplied_start_date_is_rejected_by_the_server(): void
    {
        $this->postJson('/api/contracts/scholarship', $this->payload([
            'start_date' => now()->toDateString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_the_client_service_contract_schema_does_not_require_a_prohibited_field(): void
    {
        $clientKeys = $this->clientServiceContractKeys();

        $this->assertNotEmpty($clientKeys, 'Could not read serviceContractRules from apiValidation.ts.');
        $this->assertArrayNotHasKey('start_date', $clientKeys, 'start_date is server-prohibited and must not be a client rule.');

        foreach ([BuiltInService::SCHOLARSHIP, BuiltInService::TEST_OFFER, BuiltInService::ACADEMY, BuiltInService::PRIVATE] as $service) {
            $serverRules = $this->serverContractRules($service);

            foreach ($clientKeys as $field => $clientRule) {
                $rules = $serverRules[$field] ?? [];

                $this->assertNotContains(
                    'prohibited',
                    $rules,
                    "The client schema sends `{$field}` for {$service}, but the server prohibits it.",
                );

                if (str_contains($clientRule, "'required'")) {
                    $this->assertNotEmpty(
                        $rules,
                        "The client schema requires `{$field}` for {$service}, but the server has no rule for it.",
                    );
                }
            }
        }
    }

    /**
     * Read the shared client schema without adding a JavaScript test runtime.
     *
     * @return array<string, string> keyed by field name, with its rule list text
     */
    private function clientServiceContractKeys(): array
    {
        $source = file_get_contents(base_path('resources/ts/utils/apiValidation.ts'));

        $this->assertIsString($source);

        preg_match('/const serviceContractRules: Schema = \{(.*?)\n\}/s', $source, $block);

        $this->assertNotEmpty($block[1] ?? null, 'serviceContractRules block not found in apiValidation.ts.');

        preg_match_all('/(?:^|,)\s*([a-z_]+)\s*:\s*\[([^\]]*)\]/m', $block[1], $matches, PREG_SET_ORDER);

        $keys = [];

        foreach ($matches as $match) {
            $keys[$match[1]] = $match[2];
        }

        return $keys;
    }

    /** The server request rules for one of the four POST routes. */
    private function serverContractRules(string $service): array
    {
        $request = StoreServiceContractRequest::create('/api/contracts/'.$service, 'POST');

        // rules() only needs `$this->route('service')`; a bound Route instance
        // would need a full request binding, so provide the minimal shape.
        $route = new class($service) {
            public function __construct(private readonly string $service) {}

            public function parameter(string $name): mixed
            {
                return $name === 'service' ? $this->service : null;
            }
        };
        $request->setRouteResolver(static fn () => $route);

        return $request->rules();
    }
}

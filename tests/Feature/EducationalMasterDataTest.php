<?php
namespace Tests\Feature;

use App\Models\Applicant;
use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Role;
use App\Models\TestOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EducationalMasterDataTest extends TestCase { use RefreshDatabase;
 protected function setUp(): void { parent::setUp(); $user=User::factory()->create(); $user->roles()->attach(Role::firstOrCreate(['name'=>'super_admin'])); Sanctum::actingAs($user,['access']); }
 public function test_country_and_university_crud_are_connected(): void { $country=$this->postJson('/api/educational-master-data/countries',['name'=>'Canada','code'=>'CAN'])->assertCreated()->json('data'); $this->postJson('/api/educational-master-data/universities',['name'=>'Example University','country_id'=>$country['id'],'is_active'=>true])->assertCreated()->assertJsonPath('data.country.id',$country['id']); }

 public function test_every_catalog_has_server_side_crud_authorization_and_reference_protection(): void
 {
  $unprivileged = User::factory()->create(); Sanctum::actingAs($unprivileged, ['access']);
  $this->getJson('/api/educational-master-data/countries')->assertForbidden();
  $superAdmin = User::factory()->create(); $superAdmin->roles()->attach(Role::firstOrCreate(['name' => 'super_admin'])); Sanctum::actingAs($superAdmin, ['access']);

  $country = $this->postJson('/api/educational-master-data/countries', ['name' => 'Canada', 'code' => 'CAN'])->assertCreated()->json('data');
  $level = $this->postJson('/api/educational-master-data/education-levels', ['name' => 'Bachelor', 'description' => 'Undergraduate', 'sort_order' => 20])->assertCreated()->json('data');
  $language = $this->postJson('/api/educational-master-data/languages', ['name' => 'English', 'code' => 'en'])->assertCreated()->json('data');
  $package = $this->postJson('/api/educational-master-data/package-types', ['name' => 'Admission package', 'service_type' => 'admission'])->assertCreated()->json('data');
  $university = $this->postJson('/api/educational-master-data/universities', ['name' => 'Example University', 'country_id' => $country['id'], 'city' => 'Ottawa'])->assertCreated()->json('data');

  $this->putJson("/api/educational-master-data/education-levels/{$level['id']}", ['description' => 'Four-year undergraduate'])->assertOk()->assertJsonPath('data.description', 'Four-year undergraduate');
  $this->putJson("/api/educational-master-data/languages/{$language['id']}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
  $this->getJson('/api/educational-master-data/languages?active=1')->assertOk()->assertJsonMissing(['id' => $language['id']]);

  $customer = Customer::factory()->create();
  Applicant::create(['customer_id' => $customer->id, 'country_id' => $country['id'], 'university_id' => $university['id'], 'education_level_id' => $level['id'], 'language_id' => $language['id']]);
  $template = ContractTemplate::create(['name' => 'Admission agreement', 'contract_type_id' => ContractType::firstOrCreate(['slug' => 'educational-service'], ['name' => 'Educational service'])->id, 'cover_text' => '', 'content' => '<p>Legacy</p>']);
  ContractTemplateVersion::create(['contract_template_id' => $template->id, 'language_id' => $language['id'], 'version_number' => 1, 'content' => '<p>Version 1</p>']);
  TestOffer::create(['customer_id' => $customer->id, 'employee_id' => Employee::factory()->create()->id, 'currency_id' => Currency::factory()->create()->id, 'package_type_id' => $package['id'], 'date' => now()->toDateString(), 'fee' => 100]);

  foreach ([['countries', $country['id']], ['education-levels', $level['id']], ['languages', $language['id']], ['package-types', $package['id']], ['universities', $university['id']]] as [$catalog, $id]) $this->deleteJson("/api/educational-master-data/{$catalog}/{$id}")->assertConflict()->assertJsonPath('code', 'business.master_data_in_use');

  $unused = $this->postJson('/api/educational-master-data/package-types', ['name' => 'Unused package'])->assertCreated()->json('data');
  $this->deleteJson("/api/educational-master-data/package-types/{$unused['id']}")->assertNoContent();
  $this->assertSoftDeleted('package_types', ['id' => $unused['id']]);
 }
}

<?php
namespace Tests\Feature;
use App\Models\Role; use App\Models\User; use Illuminate\Foundation\Testing\RefreshDatabase; use Laravel\Sanctum\Sanctum; use Tests\TestCase;
class EducationalMasterDataTest extends TestCase { use RefreshDatabase;
 protected function setUp(): void { parent::setUp(); $user=User::factory()->create(); $user->roles()->attach(Role::firstOrCreate(['name'=>'super_admin'])); Sanctum::actingAs($user,['access']); }
 public function test_country_and_university_crud_are_connected(): void { $country=$this->postJson('/api/educational-master-data/countries',['name'=>'Canada','code'=>'CAN'])->assertCreated()->json('data'); $this->postJson('/api/educational-master-data/universities',['name'=>'Example University','country_id'=>$country['id'],'is_active'=>true])->assertCreated()->assertJsonPath('data.country.id',$country['id']); }
}

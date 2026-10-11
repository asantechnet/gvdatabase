<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\ContractTemplateVersion;
use App\Models\ContractType;
use App\Models\Country;
use App\Models\Language;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BuiltInContractTemplateMigrationTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        RefreshDatabaseState::$migrated = false;
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        RefreshDatabaseState::$migrated = false;
    }

    public function test_round_trip_preserves_legacy_templates_and_their_versions(): void
    {
        $template = ContractTemplate::create([
            'name' => 'Legacy terms', 'contract_type_id' => ContractType::where('slug', 'customer')->firstOrFail()->id,
            'content' => '<p>Historical agreement</p>', 'cover_text' => '',
        ]);
        $country = Country::create(['name' => 'Destination', 'code' => 'DS']);
        $language = Language::create(['name' => 'English', 'code' => 'en']);
        $version = ContractTemplateVersion::create([
            'contract_template_id' => $template->id, 'country_id' => $country->id,
            'language_id' => $language->id, 'name' => 'Original version',
            'version_number' => 1, 'content' => '<p>Original terms</p>', 'status' => 'draft',
        ]);
        $migration = require database_path('migrations/2026_10_07_000009_add_builtin_service_to_contract_templates.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('contract_templates', 'service'));
        $this->assertDatabaseHas('contract_template_versions', ['id' => $version->id, 'contract_template_id' => $template->id]);
        $migration->up();
        $this->assertTrue(Schema::hasColumn('contract_templates', 'service'));
        $this->assertDatabaseHas('contract_templates', ['id' => $template->id, 'service' => null, 'content' => '<p>Historical agreement</p>']);
        $this->assertDatabaseHas('contract_template_versions', ['id' => $version->id, 'content' => '<p>Original terms</p>']);
        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        }
    }
}

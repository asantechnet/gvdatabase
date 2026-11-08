<?php

namespace Tests\Feature;

use App\Services\Contracts\PlaceholderRegistry;
use App\Services\Contracts\PlaceholderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademyContractDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_end_date_is_declared_and_resolves_with_a_safe_fallback(): void
    {
        $this->assertContains('contract.end_date', PlaceholderRegistry::keysForSlug('customer'));
        $resolver = app(PlaceholderResolver::class);

        $rendered = $resolver->resolve(
            '{{ contract.start_date }} to {{ contract.end_date }}',
            ['start_date' => '2027-01-01', 'end_date' => '2027-03-31'],
            'customer',
        );
        $this->assertSame(['content' => '2027-01-01 to 2027-03-31', 'unresolved' => [], 'missing' => []], $rendered);

        $legacy = $resolver->resolve('Term: {{ contract.end_date }}', ['start_date' => '2027-01-01'], 'customer');
        $this->assertSame('Term: ', $legacy['content']);
        $this->assertSame(['contract.end_date'], $legacy['missing']);
    }

    public function test_unknown_placeholder_is_still_refused(): void
    {
        $rendered = app(PlaceholderResolver::class)->resolve('{{ contract.unknown_date }}', [], 'customer');
        $this->assertSame(['{{ contract.unknown_date }}'], $rendered['unresolved']);
    }

    public function test_academy_template_seeder_contains_the_term_placeholders(): void
    {
        \App\Models\Language::create(['name' => 'English', 'code' => 'en']);
        $this->seed(\Database\Seeders\AcademyContractTemplateSeeder::class);
        $this->assertTrue(\App\Models\ContractTemplateVersion::query()
            ->where('content', 'like', '%{contract.start_date}%')
            ->where('content', 'like', '%{contract.end_date}%')
            ->exists());
    }
}

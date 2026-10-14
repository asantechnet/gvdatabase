<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give every built-in service its own stage list.
 *
 * A single shared pipeline cannot describe a scholarship visa case, a test
 * offer and an academy enrolment at the same time, so stages become
 * service-scoped. Existing rows are kept as the scholarship pipeline, reused
 * for private contracts, and concise pipelines are seeded for test offers and
 * academy enrolments.
 *
 * `key` is no longer globally unique: the same key (for example `new`) can
 * exist once per service. A composite `(service, key)` uniqueness replaces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('applicant_stages', 'service')) {
            Schema::table('applicant_stages', function (Blueprint $table): void {
                $table->string('service')->nullable()->index();
            });
        }

        // Replace the global key uniqueness with a per-service one.
        Schema::table('applicant_stages', function (Blueprint $table): void {
            $table->dropUnique('applicant_stages_key_unique');
        });
        Schema::table('applicant_stages', function (Blueprint $table): void {
            $table->unique(['service', 'key'], 'applicant_stages_service_key_unique');
        });

        // Existing rows are the visa pipeline: they serve scholarship and private.
        DB::table('applicant_stages')->whereNull('service')->update(['service' => 'scholarship']);
        $this->copyService('scholarship', 'private');
        $this->seedService('test_offer', $this->testOfferStages());
        $this->seedService('academy', $this->academyStages());
    }

    public function down(): void
    {
        DB::table('applicant_stages')->whereIn('service', ['private', 'test_offer', 'academy'])->delete();
        DB::table('applicant_stages')->where('service', 'scholarship')->update(['service' => null]);

        Schema::table('applicant_stages', function (Blueprint $table): void {
            $table->dropUnique('applicant_stages_service_key_unique');
        });
        Schema::table('applicant_stages', function (Blueprint $table): void {
            $table->unique('key');
        });
        if (Schema::hasColumn('applicant_stages', 'service')) {
            Schema::table('applicant_stages', function (Blueprint $table): void {
                $table->dropColumn('service');
            });
        }
    }

    /** Duplicate every stage of one service into another (same keys/edges). */
    private function copyService(string $from, string $to): void
    {
        if (DB::table('applicant_stages')->where('service', $to)->exists()) {
            return;
        }
        $now = now();
        foreach (DB::table('applicant_stages')->where('service', $from)->orderBy('sort_order')->get() as $row) {
            DB::table('applicant_stages')->insert([
                'service' => $to,
                'key' => $row->key,
                'name' => $row->name,
                'sort_order' => $row->sort_order,
                'is_active' => $row->is_active,
                'is_terminal' => $row->is_terminal,
                'allowed_next_keys' => $row->allowed_next_keys,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** @param array<int, array{key: string, name: string, sort: int, terminal: bool, next: string[]}> $rows */
    private function seedService(string $service, array $rows): void
    {
        if (DB::table('applicant_stages')->where('service', $service)->exists()) {
            return;
        }
        $now = now();
        foreach ($rows as $row) {
            DB::table('applicant_stages')->insert([
                'service' => $service,
                'key' => $row['key'],
                'name' => $row['name'],
                'sort_order' => $row['sort'],
                'is_active' => true,
                'is_terminal' => $row['terminal'],
                'allowed_next_keys' => json_encode($row['next']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function testOfferStages(): array
    {
        return [
            ['key' => 'new', 'name' => 'New', 'sort' => 10, 'terminal' => false, 'next' => ['documents_required', 'cancelled', 'withdrawn']],
            ['key' => 'documents_required', 'name' => 'Documents Required', 'sort' => 20, 'terminal' => false, 'next' => ['registered', 'cancelled', 'withdrawn']],
            ['key' => 'registered', 'name' => 'Registered', 'sort' => 30, 'terminal' => false, 'next' => ['test_scheduled', 'cancelled', 'withdrawn']],
            ['key' => 'test_scheduled', 'name' => 'Test Scheduled', 'sort' => 40, 'terminal' => false, 'next' => ['test_completed', 'cancelled', 'withdrawn']],
            ['key' => 'test_completed', 'name' => 'Test Completed', 'sort' => 50, 'terminal' => false, 'next' => ['completed', 'cancelled', 'withdrawn']],
            ['key' => 'completed', 'name' => 'Completed', 'sort' => 60, 'terminal' => true, 'next' => []],
            ['key' => 'cancelled', 'name' => 'Cancelled', 'sort' => 900, 'terminal' => true, 'next' => []],
            ['key' => 'withdrawn', 'name' => 'Withdrawn', 'sort' => 920, 'terminal' => true, 'next' => []],
        ];
    }

    private function academyStages(): array
    {
        return [
            ['key' => 'new', 'name' => 'New', 'sort' => 10, 'terminal' => false, 'next' => ['enrolled', 'cancelled', 'withdrawn']],
            ['key' => 'enrolled', 'name' => 'Enrolled', 'sort' => 20, 'terminal' => false, 'next' => ['in_class', 'cancelled', 'withdrawn']],
            ['key' => 'in_class', 'name' => 'In Class', 'sort' => 30, 'terminal' => false, 'next' => ['completed', 'cancelled', 'withdrawn']],
            ['key' => 'completed', 'name' => 'Completed', 'sort' => 40, 'terminal' => true, 'next' => []],
            ['key' => 'cancelled', 'name' => 'Cancelled', 'sort' => 900, 'terminal' => true, 'next' => []],
            ['key' => 'withdrawn', 'name' => 'Withdrawn', 'sort' => 920, 'terminal' => true, 'next' => []],
        ];
    }
};

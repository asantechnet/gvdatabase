<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reference section: country / education-level / language / package-type
 * catalogues, universities, semesters and the applicant stage machine.
 *
 * The stage rows are seeded with the FINAL edges: cancellations and
 * withdrawals are reachable from every active stage, refusals from the
 * decision stages, and appeals/re-engagement back into the pipeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 3)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 10)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('package_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('service_type')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('fee', 20, 6)->nullable();
            $table->json('service_manifest')->nullable();
        });

        Schema::create('universities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('city')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'country_id']);
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 30)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('applicant_stages', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('university_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_terminal')->default(false);
            $table->json('allowed_next_keys')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['country_id', 'university_id', 'is_active'], 'applicant_stage_context_index');
        });

        $this->seedStages();
    }

    private function seedStages(): void
    {
        $now = now();
        $exits = ['cancelled', 'withdrawn'];
        $happyPath = [
            'new'                   => ['consultation'],
            'consultation'          => ['documents_required'],
            'documents_required'    => ['documents_complete'],
            'documents_complete'    => ['university_selection'],
            'university_selection'  => ['university_application'],
            'university_application'=> ['admission_pending'],
            'admission_pending'     => ['admission_received'],
            'admission_received'    => ['contract_service_active'],
            'contract_service_active' => ['visa_preparation'],
            'visa_preparation'      => ['visa_submitted'],
            'visa_submitted'        => ['visa_processing'],
            'visa_processing'       => ['visa_approved'],
            'visa_approved'         => ['completed'],
        ];
        $decisionStages = ['admission_pending', 'visa_submitted', 'visa_processing'];

        $rows = [];
        $sortOrder = 10;
        foreach ($happyPath as $key => $next) {
            $rows[] = [
                'key' => $key,
                'name' => ucwords(str_replace('_', ' ', $key)),
                'sort_order' => $sortOrder,
                'is_terminal' => false,
                'next' => array_values(array_unique([...$next, ...$exits, ...(in_array($key, $decisionStages, true) ? ['rejected'] : [])])),
            ];
            $sortOrder += 10;
        }

        foreach ([['completed', 140], ['cancelled', 900], ['rejected', 910], ['withdrawn', 920]] as [$key, $order]) {
            $rows[] = [
                'key' => $key,
                'name' => ucfirst($key),
                'sort_order' => $order,
                'is_terminal' => true,
                // Appeals / re-engagement back into the pipeline.
                'next' => match ($key) {
                    'rejected' => ['university_selection', 'visa_preparation'],
                    'withdrawn' => ['consultation'],
                    default => [],
                },
            ];
        }

        foreach ($rows as $row) {
            DB::table('applicant_stages')->insert([
                'key' => $row['key'],
                'name' => $row['name'],
                'sort_order' => $row['sort_order'],
                'is_active' => true,
                'is_terminal' => $row['is_terminal'],
                'allowed_next_keys' => json_encode($row['next']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('applicant_stages');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('universities');
        Schema::dropIfExists('package_types');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('education_levels');
        Schema::dropIfExists('countries');
    }
};

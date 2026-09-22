<?php

use App\Models\CustomerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('category', 40)->default('general')->after('type')->index();
            $table->text('description')->nullable()->after('category');
        });

        Schema::table('package_types', function (Blueprint $table): void {
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('fee', 20, 6)->nullable();
            $table->json('service_manifest')->nullable();
        });

        Schema::create('package_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('package_types')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->decimal('allocated_fee', 20, 6)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['package_id', 'service_id']);
        });

        Schema::create('customer_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->constrained('package_types')->restrictOnDelete();
            $table->foreignId('sold_by_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('currency_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('fee', 20, 6)->nullable();
            $table->date('purchased_at');
            $table->string('status', 30)->default('active');
            $table->string('issuance_key')->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['customer_id', 'applicant_id', 'status']);
        });

        // MySQL may use the old composite unique index to support the customer
        // foreign key. Give that FK its own index before removing the uniqueness.
        Schema::table('customer_service', fn (Blueprint $table) => $table->index('customer_id', 'customer_service_customer_id_idx'));
        Schema::table('customer_service', function (Blueprint $table): void {
            $table->dropUnique(['customer_id', 'service_id']);
            $table->foreignId('applicant_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->foreignId('customer_package_id')->nullable()->after('service_id')->constrained('customer_packages')->nullOnDelete();
            $table->foreignId('sold_by_employee_id')->nullable()->after('employee_id')->constrained('employees')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('language_id')->nullable()->constrained()->nullOnDelete();
            $table->date('sold_at')->nullable();
            $table->string('legacy_source_type')->nullable();
            $table->unsignedBigInteger('legacy_source_id')->nullable();
            $table->index(['applicant_id', 'status']);
            $table->unique(['customer_package_id', 'service_id'], 'customer_package_service_unique');
            $table->unique(['legacy_source_type', 'legacy_source_id'], 'customer_service_legacy_source_unique');
        });

        Schema::create('case_wins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('applicant_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('applicant_stage_history_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamp('won_at');
            $table->string('stage_key', 100);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['employee_id', 'won_at']);
        });

        $this->migrateLegacyEducationalSales();
        $this->backfillCaseWins();
        $this->installPermissions();
    }

    private function migrateLegacyEducationalSales(): void
    {
        $now = now();

        foreach (['scholarships' => 'scholarship', 'test_offers' => 'test_offer'] as $table => $category) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $first = DB::table($table)->whereNull('deleted_at')->orderBy('id')->first();
            if (! $first) {
                continue;
            }

            $name = $category === 'scholarship' ? 'Scholarship Assistance' : 'Test Offer';
            $serviceId = DB::table('services')->where('category', $category)->value('id');
            if (! $serviceId) {
                $serviceId = DB::table('services')->insertGetId([
                    'type' => $name,
                    'category' => $category,
                    'description' => 'Migrated educational service catalog entry.',
                    'duration' => 1,
                    'fee' => 0,
                    'status' => 'active',
                    'currency_id' => $first->currency_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table($table)->orderBy('id')->each(function (object $sale) use ($table, $category, $serviceId): void {
                $customerServiceId = DB::table('customer_service')->insertGetId([
                    'customer_id' => $sale->customer_id,
                    'applicant_id' => $sale->applicant_id,
                    'service_id' => $serviceId,
                    'employee_id' => $sale->employee_id,
                    'sold_by_employee_id' => $sale->employee_id,
                    'fee' => $sale->fee,
                    'currency_id' => $sale->currency_id,
                    'country_id' => $sale->country_id ?? null,
                    'education_level_id' => $sale->education_level_id ?? null,
                    'language_id' => $sale->language_id ?? null,
                    'sold_at' => $sale->date,
                    'status' => $sale->status === 'cancelled' ? 'cancelled' : $sale->status,
                    'legacy_source_type' => $category,
                    'legacy_source_id' => $sale->id,
                    'created_at' => $sale->created_at,
                    'updated_at' => $sale->updated_at,
                    'deleted_at' => $sale->deleted_at,
                ]);

                DB::table('educational_bonus_sources')
                    ->where('source_type', $table === 'scholarships' ? 'App\\Models\\Scholarship' : 'App\\Models\\TestOffer')
                    ->where('source_id', $sale->id)
                    ->update(['source_type' => CustomerService::class, 'source_id' => $customerServiceId]);
            });
        }

        DB::table('package_types')->whereNotNull('service_type')->orderBy('id')->each(function (object $package): void {
            $serviceId = DB::table('services')->where('category', $package->service_type)->value('id');
            if ($serviceId) {
                DB::table('package_service')->insertOrIgnore([
                    'package_id' => $package->id,
                    'service_id' => $serviceId,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
        DB::table('package_types')->orderBy('id')->each(function (object $package): void {
            DB::table('package_types')->where('id', $package->id)->update([
                'service_manifest' => json_encode(DB::table('package_service')->where('package_id', $package->id)->orderBy('sort_order')->pluck('service_id')->all()),
            ]);
        });
    }

    private function backfillCaseWins(): void
    {
        $winningStageIds = DB::table('applicant_stages')->whereIn('key', ['visa_approved', 'completed'])->pluck('id', 'key');
        if ($winningStageIds->isEmpty()) {
            return;
        }

        DB::table('applicants')->whereNotNull('responsible_employee_id')->orderBy('id')->each(function (object $applicant) use ($winningStageIds): void {
            $history = DB::table('applicant_stage_histories')
                ->where('applicant_id', $applicant->id)
                ->whereIn('new_stage_id', $winningStageIds->values())
                ->orderByRaw('CASE WHEN new_stage_id = ? THEN 0 ELSE 1 END', [$winningStageIds['visa_approved'] ?? 0])
                ->orderBy('changed_at')
                ->first();
            if (! $history) {
                return;
            }
            $stageKey = $winningStageIds->search($history->new_stage_id, true) ?: 'completed';
            DB::table('case_wins')->insertOrIgnore([
                'applicant_id' => $applicant->id,
                'employee_id' => $applicant->responsible_employee_id,
                'applicant_stage_history_id' => $history->id,
                'won_at' => $history->changed_at,
                'stage_key' => $stageKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    private function installPermissions(): void
    {
        $now = now();
        foreach (['view_packages', 'manage_packages', 'issue_packages'] as $name) {
            DB::table('permissions')->updateOrInsert(['name' => $name], [
                'category' => 'educational_visa', 'guard_name' => 'web', 'deleted_at' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $grants = [
            'admin' => ['view_packages', 'manage_packages', 'issue_packages'],
            'manager' => ['view_packages', 'manage_packages', 'issue_packages'],
            'staff' => ['view_packages', 'issue_packages'],
        ];
        foreach ($grants as $roleName => $permissions) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if (! $roleId) {
                continue;
            }
            foreach ($permissions as $permission) {
                $permissionId = DB::table('permissions')->where('name', $permission)->value('id');
                DB::table('role_permissions')->insertOrIgnore(['role_id' => $roleId, 'permission_id' => $permissionId, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', ['view_packages', 'manage_packages', 'issue_packages'])->pluck('id');
        DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        Schema::dropIfExists('case_wins');
        Schema::table('customer_service', function (Blueprint $table): void {
            $table->dropUnique('customer_package_service_unique');
            $table->dropUnique('customer_service_legacy_source_unique');
            $table->dropConstrainedForeignId('applicant_id');
            $table->dropConstrainedForeignId('customer_package_id');
            $table->dropConstrainedForeignId('sold_by_employee_id');
            $table->dropConstrainedForeignId('country_id');
            $table->dropConstrainedForeignId('education_level_id');
            $table->dropConstrainedForeignId('language_id');
            $table->dropColumn(['sold_at', 'legacy_source_type', 'legacy_source_id']);
            $table->unique(['customer_id', 'service_id']);
            $table->dropIndex('customer_service_customer_id_idx');
        });
        Schema::dropIfExists('customer_packages');
        Schema::dropIfExists('package_service');
        Schema::table('package_types', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('currency_id');
            $table->dropColumn(['fee', 'service_manifest']);
        });
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['category', 'description']));
    }
};

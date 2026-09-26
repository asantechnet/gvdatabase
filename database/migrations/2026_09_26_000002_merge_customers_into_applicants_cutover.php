<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DESTRUCTIVE step of the customers → applicants merge.
 *
 * One entity remains: `applicants` owns both the billing/CRM party data and
 * the visa-case data. `customers` ceases to exist.
 *
 * 1. Folds every `customers` row into `applicants`:
 *      - a customer that already has cases donates its party columns to ALL of
 *        them, and its earliest case becomes the group root
 *        (`applicants.parent_applicant_id`),
 *      - a customer with NO case is promoted to a case of its own — this is the
 *        actual merge, the customers directory becomes the applicants directory.
 * 2. Re-points every `customer_id` foreign key at `applicants.id`. On tables
 *    that already carried `applicant_id` the explicit case link wins and
 *    `customer_id` only backfills the gaps; on `invoices`/`projects`, which had
 *    no case link at all, `customer_id` becomes `applicant_id`.
 * 3. Re-points the `crm_interactions` polymorphic `Customer` rows at
 *    `App\Models\Applicant`, and rewrites `customer_packages.issuance_key`
 *    values from `customer:{id}:` to `applicant:{id}:`.
 * 4. Deletes `applicants.customer_id`, every other `customer_id` column, the
 *    `customers` table, and the `*_customers` permissions — after granting the
 *    applicant equivalents to any role that held them.
 *
 * NOT touched on purpose (see warning.md):
 *  - `contract_types.slug = 'customer'` — a contract type, not this entity.
 *  - `invoices.customer_company/email/contact/address/tax_number` and
 *    `invoices.deptor` — immutable snapshots of issued documents (see AI.md).
 *
 * down() only reverses the schema. Party columns that were folded into existing
 * cases cannot be split back out, so down() recreates an empty shell.
 */
return new class extends Migration
{
    /** Tables that carried `customer_id` AND `applicant_id`: backfill, then drop. */
    private const DUAL_KEY_TABLES = [
        'contracts',
        'customer_packages',
        'educational_bonus_sources',
        'expenses',
        'incomes',
        'leads',
        'pending_transactions',
        'scholarships',
        'tasks',
        'test_offers',
    ];

    /** Tables that carried only `customer_id`: the column becomes `applicant_id`. */
    private const PARTY_ONLY_TABLES = ['invoices', 'projects'];

    /** Old `*_customers` permission → the applicant permission that replaces it. */
    private const PERMISSION_MAP = [
        'view_customers' => 'view_applicants',
        'create_customers' => 'create_applicants',
        'edit_customers' => 'edit_applicants',
        'delete_customers' => 'delete_applicants',
    ];

    public function up(): void
    {
        $this->foldCustomersIntoApplicants();
        $this->repointForeignKeys();
        $this->repointCrmInteractions();
        $this->rewriteIssuanceKeys();
        $this->migratePermissions();
        $this->dropCustomerColumns();
    }

    public function down(): void
    {
        // Recreate a `customers` shell and the `customer_id` columns so a
        // rollback does not leave the schema unusable. Party data is not
        // recoverable, and no attempt is made to invent it.
        Schema::create('customers', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('marketer')->nullable();
            $table->foreignId('marketer_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->date('date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        foreach (array_merge(self::DUAL_KEY_TABLES, self::PARTY_ONLY_TABLES, ['applicants']) as $tableName) {
            Schema::table($tableName, function (\Illuminate\Database\Schema\Blueprint $table) use ($tableName): void {
                $table->foreignId('customer_id')->nullable()->after('id')->constrained('customers')->nullOnDelete();
            });
        }
    }

    /**
     * Step 1 — every customer becomes applicant party data.
     */
    private function foldCustomersIntoApplicants(): void
    {
        $now = now();

        // 1a. A customer with no case at all is promoted to a case of its own.
        //     `status` keeps the column default; case dimensions stay NULL
        //     because a billing-only record has no case to describe.
        DB::table('customers')->orderBy('id')->each(function (object $customer) use ($now): void {
            $hasCase = DB::table('applicants')->where('customer_id', $customer->id)->exists();
            if ($hasCase) {
                return;
            }

            DB::table('applicants')->insert([
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'company' => $customer->company,
                'contact' => $customer->contact,
                'email' => $customer->email,
                'address' => $customer->address,
                'tax_number' => $customer->tax_number,
                'marketer' => $customer->marketer,
                'marketer_id' => $customer->marketer_id,
                'date' => $customer->date,
                'notes' => $customer->notes,
                'status' => 'active',
                'application_date' => $customer->date,
                'created_at' => $customer->created_at ?: $now,
                'updated_at' => $customer->updated_at ?: $now,
                'deleted_at' => $customer->deleted_at,
            ]);
        });

        // 1b. Donate the party columns to every case that shared the customer, so
        //     no case is left without a name once `name` becomes NOT NULL.
        foreach (['name', 'company', 'contact', 'email', 'address', 'tax_number', 'marketer', 'marketer_id', 'date', 'notes'] as $column) {
            DB::statement(
                'UPDATE `applicants` a JOIN `customers` c ON c.id = a.customer_id SET a.'.$column.' = c.'.$column
            );
        }

        // 1c. The earliest case of a shared customer is the group root.
        DB::statement(<<<'SQL'
            UPDATE `applicants` a
            JOIN (
                SELECT `customer_id`, MIN(`id`) AS `root_id`
                FROM `applicants`
                WHERE `customer_id` IS NOT NULL
                GROUP BY `customer_id`
            ) roots ON roots.`customer_id` = a.`customer_id`
            SET a.`parent_applicant_id` = roots.`root_id`
            WHERE a.`id` <> roots.`root_id`
        SQL);

        // 1d. Every row is populated now, so the party identity can be required.
        DB::statement("UPDATE `applicants` SET `name` = CONCAT('Applicant #', `id`) WHERE `name` IS NULL OR `name` = ''");
        Schema::table('applicants', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
        });
    }

    /**
     * Step 2 — every `customer_id` now points at `applicants.id`.
     */
    private function repointForeignKeys(): void
    {
        // 2a. Tables that already had a case link: prefer it, backfill the gaps.
        foreach (self::DUAL_KEY_TABLES as $tableName) {
            DB::statement(
                'UPDATE `'.$tableName.'` t'
                .' JOIN (SELECT `customer_id`, MIN(`id`) AS `applicant_id` FROM `applicants` WHERE `customer_id` IS NOT NULL GROUP BY `customer_id`) map'
                .' ON map.`customer_id` = t.`customer_id`'
                .' SET t.`applicant_id` = COALESCE(t.`applicant_id`, map.`applicant_id`)'
            );
        }

        // 2b. `invoices` / `projects` had no case link, so the party link is it.
        foreach (self::PARTY_ONLY_TABLES as $tableName) {
            DB::statement(
                'UPDATE `'.$tableName.'` t'
                .' JOIN (SELECT `customer_id`, MIN(`id`) AS `applicant_id` FROM `applicants` WHERE `customer_id` IS NOT NULL GROUP BY `customer_id`) map'
                .' ON map.`customer_id` = t.`customer_id`'
                .' SET t.`applicant_id` = map.`applicant_id`'
                .' WHERE t.`applicant_id` IS NULL'
            );
        }

        // 2c. `invoices.customer_id` was NOT NULL/restrict, so the case link is
        //     equally required. Soft-deleted customers still map, because the
        //     promotion step above ran over every customer row.
        $orphanInvoices = DB::table('invoices')->whereNull('applicant_id')->count();
        abort_if($orphanInvoices > 0, 500, "Cannot merge customers into applicants: {$orphanInvoices} invoice(s) have no applicant.");

        // The additive step wired this as NULL/SET NULL because it had to be
        // nullable while both links existed. The old `invoices.customer_id` was
        // NOT NULL + RESTRICT, so the case link inherits those semantics. The
        // FK has to be dropped first: a SET NULL constraint forbids NOT NULL.
        Schema::table('invoices', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->dropForeign(['applicant_id']);
            $table->foreignId('applicant_id')->nullable(false)->change();
            $table->foreign('applicant_id')->references('id')->on('applicants')->restrictOnDelete();
        });
    }

    /**
     * Step 3a — CRM interaction rows that pointed at a customer now point at
     * the applicant that absorbed it.
     */
    private function repointCrmInteractions(): void
    {
        // Hard-coded rather than `new Customer()->getMorphClass()`: this
        // migration drops the `customers` table and the merge deletes the
        // `Customer` model, so on a fresh migrate the class no longer exists.
        // No morph map is registered, so the FQCN is what is stored.
        $customerMorph = 'App\\Models\\Customer';

        DB::table('crm_interactions')
            ->where('contactable_type', $customerMorph)
            ->orderBy('id')
            ->each(function (object $interaction): void {
                $applicantId = DB::table('applicants')
                    ->where('customer_id', $interaction->contactable_id)
                    ->min('id');

                if ($applicantId) {
                    DB::table('crm_interactions')
                        ->where('id', $interaction->id)
                        ->update(['contactable_id' => $applicantId]);
                }
            });

        DB::table('crm_interactions')
            ->where('contactable_type', $customerMorph)
            ->update(['contactable_type' => 'App\\Models\\Applicant']);
    }

    /**
     * Step 3b — package issuance keys encoded `customer:{id}:`, and the
     * issuance service now keys on the applicant.
     */
    private function rewriteIssuanceKeys(): void
    {
        DB::table('customer_packages')->where('issuance_key', 'like', 'customer:%')->orderBy('id')
            ->each(function (object $package): void {
                $oldCustomerId = (int) str($package->issuance_key)->after('customer:')->before(':')->value();
                if ($oldCustomerId <= 0) {
                    return;
                }

                $applicantId = DB::table('applicants')->where('customer_id', $oldCustomerId)->min('id');
                if (! $applicantId) {
                    return;
                }

                $newKey = 'applicant:'.$applicantId.':package:'.str($package->issuance_key)->afterLast(':')->value();
                DB::table('customer_packages')->where('id', $package->id)->update(['issuance_key' => $newKey]);
            });
    }

    /**
     * Step 4 — the customer permissions fold into the applicant permissions.
     * Roles that could manage customers must not silently lose that reach, so
     * each holder is granted the applicant equivalent before the old key goes.
     */
    private function migratePermissions(): void
    {
        foreach (self::PERMISSION_MAP as $oldName => $newName) {
            $oldId = DB::table('permissions')->where('name', $oldName)->value('id');
            $newId = DB::table('permissions')->where('name', $newName)->value('id');

            if ($oldId && $newId) {
                $roleIds = DB::table('role_permissions')->where('permission_id', $oldId)->pluck('role_id')->all();
                foreach ($roleIds as $roleId) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $newId,
                    ]);
                }
            }

            if ($oldId) {
                DB::table('role_permissions')->where('permission_id', $oldId)->delete();
                DB::table('permissions')->where('id', $oldId)->delete();
            }
        }
    }

    /**
     * Step 5 — the `customer_id` columns and the `customers` table are gone.
     */
    private function dropCustomerColumns(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (array_merge(self::DUAL_KEY_TABLES, self::PARTY_ONLY_TABLES) as $tableName) {
            Schema::table($tableName, function (\Illuminate\Database\Schema\Blueprint $table) use ($tableName): void {
                $table->dropConstrainedForeignId('customer_id');
            });
        }

        Schema::table('applicants', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            // Replaces the dropped `['customer_id', 'status']` lookup index.
            $table->index(['name', 'status'], 'applicants_name_status_idx');
        });

        Schema::dropIfExists('customers');

        Schema::enableForeignKeyConstraints();
    }
};

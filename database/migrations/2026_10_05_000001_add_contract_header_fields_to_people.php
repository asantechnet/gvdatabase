<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contract print header support fields.
 *
 * The optional printed contract header shows counterparty details for the
 * employment and customer contract types. The employee profile already owned
 * name, NID, phone, education, position, salary and contract duration; the
 * emergency contact and the annual paid leave allowance were missing.
 *
 * All columns are nullable so existing employees stay valid and the fields
 * can be filled progressively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('emergency_contact_name')->nullable()->after('contact');
            $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_name');
            $table->decimal('paid_leave_days', 5, 1)->nullable()->after('contract_duration');
        });

        // Optional contract end date; when present the printed header derives
        // the contract duration from the start/end span instead of the
        // employee profile's free-text duration.
        Schema::table('contracts', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_phone', 'paid_leave_days']);
        });
    }
};

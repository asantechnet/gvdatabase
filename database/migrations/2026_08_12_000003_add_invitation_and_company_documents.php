<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_set_password')->default(false)->after('email_verified_at');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('company_signature')->nullable()->after('website_logo');
            $table->string('business_licence_document')->nullable()->after('business_licence');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE attendances MODIFY status ENUM('present','absent','paid-off-day') NOT NULL");
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::table('attendances')->where('status', 'paid-off-day')->update(['status' => 'absent']);
            DB::statement("ALTER TABLE attendances MODIFY status ENUM('present','absent') NOT NULL");
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['company_signature', 'business_licence_document']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_set_password');
        });
    }
};

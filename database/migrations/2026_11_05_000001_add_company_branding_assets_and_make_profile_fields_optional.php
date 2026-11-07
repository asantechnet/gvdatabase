<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('website_dark_logo')->nullable()->after('website_logo');
            $table->string('favicon')->nullable()->after('website_dark_logo');
            $table->string('website_title')->nullable()->change();
            $table->string('phone', 50)->nullable()->change();
            $table->text('physical_address')->nullable()->change();
            $table->string('short_company_name')->nullable()->change();
            $table->string('original_company_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['website_dark_logo', 'favicon']);
            $table->string('website_title')->nullable(false)->change();
            $table->string('phone', 50)->nullable(false)->change();
            $table->text('physical_address')->nullable(false)->change();
            $table->string('short_company_name')->nullable(false)->change();
            $table->string('original_company_name')->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('navigation_clr')->nullable();
            $table->string('cards_clr')->nullable();
            $table->string('footer_clr')->nullable();
            $table->unsignedSmallInteger('paid_off_days_per_year')->default(0);
            $table->string('website_title');
            $table->string('website_logo')->nullable();
            $table->string('company_signature')->nullable();
            $table->string('contract_letterhead')->nullable();
            $table->string('contract_cover_design')->nullable();
            $table->string('company_email')->nullable();
            $table->string('timezone', 64)->default('Asia/Kabul');
            $table->string('website_address');
            $table->string('phone', 50);
            $table->text('physical_address');
            $table->string('short_company_name');
            $table->string('original_company_name');
            $table->string('business_licence')->nullable();
            $table->time('office_open_time')->nullable();
            $table->time('office_close_time')->nullable();
            $table->unsignedSmallInteger('default_break_minutes')->default(0);
            $table->string('primary_color')->nullable();
            $table->string('secondary_color')->nullable();
            $table->string('tertiary_color')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

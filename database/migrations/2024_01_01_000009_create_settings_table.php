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
            $table->string('navigation_clr');
            $table->string('cards_clr');
            $table->string('footer_clr');
            $table->string('website_title');
            $table->string('website_logo')->nullable();
            $table->string('website_address');
            $table->string('phone', 50);
            $table->text('physical_address');
            $table->string('short_company_name');
            $table->string('original_company_name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

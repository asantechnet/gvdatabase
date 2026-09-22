<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Retains the commercial study profile for sales recorded before a case. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['scholarships', 'test_offers'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('country_id')->nullable()->after('applicant_id')->constrained()->nullOnDelete();
                $table->foreignId('education_level_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
                $table->foreignId('language_id')->nullable()->after('education_level_id')->constrained()->nullOnDelete();
                $table->index(['country_id', 'education_level_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        foreach (['scholarships', 'test_offers'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropIndex(['country_id', 'education_level_id', 'date']);
                $table->dropConstrainedForeignId('language_id');
                $table->dropConstrainedForeignId('education_level_id');
                $table->dropConstrainedForeignId('country_id');
            });
        }
    }
};

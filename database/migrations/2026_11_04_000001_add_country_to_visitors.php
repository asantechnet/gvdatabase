<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Add the destination country selected during visitor intake. */
    public function up(): void
    {
        if (Schema::hasColumn('visitors', 'country_id')) {
            return;
        }

        Schema::table('visitors', function (Blueprint $table): void {
            $table->foreignId('country_id')->nullable()->after('service')->constrained('countries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('visitors', 'country_id')) {
            return;
        }

        Schema::table('visitors', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('country_id');
        });
    }
};
